<?php

namespace App\Http\Controllers;

use App\Models\FeedbackAttachment;
use App\Models\FeedbackPost;
use App\Models\FeedbackReview;
use App\Notifications\FeedbackActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * 피드백 AI 검수 API — 개발자 맥북 Claude Code가 토큰(X-Agent-Token)으로 호출 (세션 밖, CSRF 제외).
 * 흐름: 검수 대상 조회 → 검수 등록(관리자 전용 노출) → 관리자가 오피스에서 승인 → 승인 건 조회 → 반영 결과 기록.
 */
class AgentFeedbackController extends Controller
{
    /** 검수 대상(scope=review) 또는 승인되어 작업할 글(scope=approved) 목록 */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAgent($request);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['review', 'approved'])],
        ]);

        $query = FeedbackPost::with(['author:id,display_name,username', 'comments.user:id,display_name,username', 'attachments', 'review.decider:id,display_name,username']);

        if ($validated['scope'] === 'review') {
            // 아직 검수하지 않았거나 관리자가 재검토를 요청한 미처리 글
            $query->whereIn('status', ['waiting', 'reviewing'])
                ->where(fn (Builder $q) => $q->doesntHave('review')
                    ->orWhereHas('review', fn (Builder $r) => $r->where('decision', 'revise')));
        } else {
            $query->whereNotIn('status', ['done', 'rejected'])
                ->whereHas('review', fn (Builder $r) => $r->where('decision', 'approved')->whereNull('applied_at'));
        }

        $posts = $query->orderBy('id')->limit(100)->get();

        return response()->json([
            'posts' => $posts->map(fn (FeedbackPost $p) => $this->postPayload($p))->all(),
        ]);
    }

    /** 첨부 원본 — 스크린샷 확인용 */
    public function attachment(Request $request, FeedbackAttachment $attachment)
    {
        $this->authorizeAgent($request);
        abort_unless(Storage::exists($attachment->file_path), 404);

        return Storage::response($attachment->file_path, $attachment->file_name);
    }

    /**
     * 검수 등록 — 글당 1건. 미처리(대기·검토중) 글의 첫 검수이거나 관리자가 재검토를 요청한 경우에만 허용한다
     * (승인·보류·반려·반영 완료된 검수를 에이전트가 덮어써 관리자 결정이 사라지는 것 방지).
     */
    public function storeReview(Request $request, FeedbackPost $post): JsonResponse
    {
        $this->authorizeAgent($request);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(FeedbackReview::CATEGORY_LABELS))],
            'effort' => ['nullable', Rule::in(array_keys(FeedbackReview::EFFORT_LABELS))],
            'summary' => ['required', 'string', 'max:5000'],
            'proposal' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_unless(in_array($post->status, ['waiting', 'reviewing'], true), 409, '이미 처리된 글은 검수할 수 없습니다.');
        $existing = $post->review;
        abort_if($existing && $existing->decision !== 'revise', 409, '관리자가 재검토를 요청한 검수만 다시 등록할 수 있습니다.');

        $review = FeedbackReview::updateOrCreate(
            ['feedback_post_id' => $post->id],
            [
                ...$validated,
                'reviewed_at' => now(),
                'effort' => $validated['effort'] ?? null,
                'proposal' => $validated['proposal'] ?? null,
                'decision' => 'pending',
                'decided_by' => null,
                'decided_at' => null,
                'result_note' => null,
                'applied_at' => null,
            ],
        );

        return response()->json(['ok' => true, 'review' => $review->fresh()->toPayload()], 201);
    }

    /**
     * 반영 결과 기록 — 승인된 검수만. done=true(기본)면 반영 완료로 닫고 글을 완료 처리 + 작성자 알림.
     * done=false(일부 반영)면 메모만 남기고 승인 목록에 계속 남겨 후속 작업을 이어갈 수 있게 한다.
     */
    public function storeResult(Request $request, FeedbackPost $post): JsonResponse
    {
        $this->authorizeAgent($request);

        $validated = $request->validate([
            'result_note' => ['required', 'string', 'max:5000'],
            'done' => ['nullable', 'boolean'],
        ]);

        $done = (bool) ($validated['done'] ?? true);

        // 승인 확인과 기록을 한 트랜잭션·행 잠금으로 — 그 사이 관리자가 승인을 철회하면 완료 처리되지 않는다
        [$review, $becameDone] = DB::transaction(function () use ($post, $validated, $done) {
            $review = FeedbackReview::where('feedback_post_id', $post->id)->lockForUpdate()->first();
            abort_unless($review && $review->decision === 'approved', 409, '관리자가 승인한 검수만 반영 결과를 기록할 수 있습니다.');
            abort_if($review->applied_at !== null, 409, '이미 반영 완료로 기록된 검수입니다.');

            $review->update(['result_note' => $validated['result_note'], 'applied_at' => $done ? now() : null]);

            $becameDone = $done && $post->status !== 'done';
            if ($becameDone) {
                $post->update(['status' => 'done', 'reject_reason' => null, 'done_at' => now()]);
            }

            return [$review, $becameDone];
        });

        if ($becameDone) {
            $this->notifyAuthorDone($post);
        }

        return response()->json(['ok' => true, 'status' => $post->status, 'review' => $review->fresh()->toPayload()]);
    }

    private function authorizeAgent(Request $request): void
    {
        $expected = (string) config('services.agent_events.token');
        $provided = (string) ($request->header('X-Agent-Token') ?? $request->bearerToken() ?? '');

        abort_unless($expected !== '' && $provided !== '' && hash_equals($expected, $provided), 401, '인증 실패');
    }

    private function notifyAuthorDone(FeedbackPost $post): void
    {
        try {
            $post->author?->notify(new FeedbackActivity(
                '📮 피드백 완료: '.$post->title,
                '상태가 "완료"(으)로 변경되었습니다.',
                'feedback-status-'.$post->id,
            ));
        } catch (\Throwable $e) {
            Log::warning('피드백 푸시 발송 실패: '.$e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function postPayload(FeedbackPost $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'title' => $p->title,
            'body' => $p->body,
            'page' => $p->page,
            'status' => $p->status,
            'priority' => $p->priority,
            'author' => $p->author?->display_name ?? $p->author?->username,
            'created_at' => $p->created_at->toIso8601String(),
            'comments' => $p->comments->map(fn ($c) => [
                'user' => $c->user?->display_name ?? $c->user?->username,
                'body' => $c->body,
                'created_at' => $c->created_at->toIso8601String(),
            ])->all(),
            'attachments' => $p->attachments->map(fn (FeedbackAttachment $a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'mime_type' => $a->mime_type,
                'is_video' => str_starts_with((string) $a->mime_type, 'video/'),
                'url' => url("/api/agent/feedback-attachments/{$a->id}"),
            ])->all(),
            'review' => $p->review?->toPayload(),
        ];
    }
}

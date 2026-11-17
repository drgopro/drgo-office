<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileUploads;
use App\Models\FeedbackAttachment;
use App\Models\FeedbackComment;
use App\Models\FeedbackPost;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\FeedbackActivity;
use App\Notifications\MentionAlert;
use App\Services\ImageThumbnailService;
use App\Services\MentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    use HandlesFileUploads;

    /** 페이지 선택 드롭다운 옵션 (사이드바 메뉴 + 기타) */
    public const PAGE_OPTIONS = [
        '대시보드', '캘린더', '의뢰자', '프로젝트', '견적서', '재고',
        '위키', '장비 위치', '렌탈', '방송룸', '통계', '관리', '마이페이지', '기타',
    ];

    public function index()
    {
        return view('feedback.index', [
            'pageOptions' => self::PAGE_OPTIONS,
            'isDeveloper' => $this->isDeveloper(),
            'isAdmin' => (bool) Auth::user()?->isAdmin(),
            'canSeeReviews' => (bool) Auth::user()?->canSeeFeedbackReviews(),
            // 마스터만 AI 검수 열람자를 지정 — 화면에 현재 지정 목록을 내려준다
            'reviewViewerIds' => $this->isDeveloper() ? User::feedbackReviewViewerIds() : [],
        ]);
    }

    /** 목록 + 탭별 처리 현황 카운트 */
    public function list(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:bug,feature',
            'status' => 'nullable|in:waiting,reviewing,hold,done,rejected',
            'priority' => 'nullable|in:high,medium,low',
            'page' => 'nullable|string|max:50',
            'sort' => 'nullable|in:latest,oldest,priority',
            'q' => 'nullable|string|max:100',
            'review_pending' => 'nullable|boolean',
        ]);

        $canSeeReviews = (bool) Auth::user()?->canSeeFeedbackReviews();
        $query = FeedbackPost::with(['author:id,display_name,username,role', 'comments.user:id,display_name,username,role', 'attachments'])
            ->withCount('comments')
            ->where('type', $validated['type']);

        // AI 검수는 마스터와 마스터가 지정한 열람자에게만 — 그 외 응답에는 관계 자체를 싣지 않는다
        if ($canSeeReviews) {
            $query->with('review.decider:id,display_name,username');
            if (! empty($validated['review_pending'])) {
                $query->whereHas('review', fn ($r) => $r->where('decision', 'pending'));
            }
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }
        if (! empty($validated['page'])) {
            $query->where('page', $validated['page']);
        }
        if (! empty($validated['q'])) {
            $like = '%'.$validated['q'].'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('body', 'like', $like)
                    ->orWhereHas('author', fn ($a) => $a->where('display_name', 'like', $like)->orWhere('username', 'like', $like));
            });
        }

        // 미완료 글이 최신순으로 상단, 완료 글은 하단에 완료된 순(최근 완료 먼저).
        // 정렬 키: 완료 글은 완료 시각(done_at), 미완료 글은 등록 시각
        $query->orderByRaw("case when status = 'done' then 1 else 0 end");
        $sortKey = 'coalesce(done_at, created_at)';
        if (($validated['sort'] ?? 'latest') === 'priority') {
            // 우선순위순: 높음 → 중간 → 낮음 → 미지정, 같은 순위 안에서는 최신순
            $query->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 when 'low' then 2 else 3 end")
                ->orderByRaw("{$sortKey} desc");
        } else {
            $query->orderByRaw($sortKey.(($validated['sort'] ?? 'latest') === 'oldest' ? ' asc' : ' desc'));
        }

        $posts = $query->limit(200)
            ->get()
            ->map(fn ($p) => $this->postPayload($p));

        return response()->json([
            'posts' => $posts,
            'counts' => $this->statusCounts($validated['type']),
            'tab_totals' => [
                'bug' => FeedbackPost::where('type', 'bug')->count(),
                'feature' => FeedbackPost::where('type', 'feature')->count(),
            ],
            'review_pending_count' => $canSeeReviews
                ? FeedbackPost::where('type', $validated['type'])->whereHas('review', fn ($r) => $r->where('decision', 'pending'))->count()
                : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->postBodyWasDropped($request)) {
            return response()->json(['message' => $this->uploadLimitMessage()], 422);
        }

        $validated = $request->validate([
            'type' => 'required|in:bug,feature',
            'title' => 'required|string|max:200',
            'body' => 'nullable|string|max:5000',
            'page' => 'required|string|max:50',
            'files' => [$request->input('type') === 'bug' ? 'required' : 'nullable', 'array'],
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm|max:112640',
        ], [
            'page.required' => '어떤 페이지인지 선택해주세요.',
            'files.required' => '버그 제보에는 스크린샷 첨부가 필수입니다.',
            'files.*.mimes' => '이미지 또는 영상(mp4/mov/webm) 파일만 첨부할 수 있습니다.',
            'files.*.max' => '파일당 최대 110MB까지 첨부할 수 있습니다.',
        ]);

        $post = FeedbackPost::create([
            'type' => $validated['type'],
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'page' => $validated['page'],
            'created_by' => Auth::id(),
        ]);

        if ($request->hasFile('files')) {
            $this->storeUploadedFiles(
                $request->file('files'),
                "feedback/{$post->id}",
                function ($file, $path) use ($post) {
                    $post->attachments()->create([
                        'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            );
        }

        $this->notifyDevelopers($post, '📮 새 '.($post->type === 'bug' ? '버그 제보' : '기능 요청').': '.$post->title);

        return response()->json($this->postPayload($post->load(['author:id,display_name,username,role', 'attachments'])->loadCount('comments')), 201);
    }

    public function update(Request $request, FeedbackPost $post): JsonResponse
    {
        $this->authorizeEdit($post);

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string|max:5000',
            'page' => 'required|string|max:50',
        ]);

        $post->update($validated);

        return response()->json($this->postPayload($post->fresh(['author:id,display_name,username,role', 'comments.user:id,display_name,username,role', 'attachments'])->loadCount('comments')));
    }

    public function destroy(FeedbackPost $post): JsonResponse
    {
        $this->authorizeEdit($post);

        $post->delete();

        return response()->json(['ok' => true]);
    }

    /** 첨부 추가 — 작성자(잠금 전) 또는 관리자 */
    public function storeAttachments(Request $request, FeedbackPost $post): JsonResponse
    {
        $this->authorizeEdit($post);

        if ($this->postBodyWasDropped($request)) {
            return response()->json(['message' => $this->uploadLimitMessage()], 422);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm|max:112640',
        ], [
            'files.*.mimes' => '이미지 또는 영상(mp4/mov/webm) 파일만 첨부할 수 있습니다.',
            'files.*.max' => '파일당 최대 110MB까지 첨부할 수 있습니다.',
        ]);

        $this->storeUploadedFiles(
            $request->file('files'),
            "feedback/{$post->id}",
            function ($file, $path) use ($post) {
                $post->attachments()->create([
                    'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        );

        return response()->json($this->postPayload($post->fresh(['author:id,display_name,username,role', 'comments.user:id,display_name,username,role', 'attachments'])->loadCount('comments')));
    }

    /** 첨부 삭제 — 작성자(잠금 전) 또는 관리자 */
    public function destroyAttachment(FeedbackAttachment $attachment): JsonResponse
    {
        $this->authorizeEdit($attachment->post);

        Storage::delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    /** 상태 변경 — 개발자(master) 전용. 반려는 사유 필수 */
    /** 우선순위 지정 — 관리자(master/admin) 전용. null이면 해제. 전 멤버에게 배지로 노출된다 */
    public function updatePriority(Request $request, FeedbackPost $post): JsonResponse
    {
        abort_unless((bool) Auth::user()?->isAdmin(), 403, '관리자만 우선순위를 지정할 수 있습니다.');

        $validated = $request->validate([
            'priority' => 'nullable|in:high,medium,low',
        ]);

        $post->update(['priority' => $validated['priority'] ?? null]);

        return response()->json(['ok' => true, 'priority' => $post->priority]);
    }

    /**
     * AI 검수 결정 — 마스터와 마스터가 지정한 열람자 전용.
     * 승인하면 글이 "검토중"(수락)으로 넘어가고, 에이전트가 승인 건만 가져가 수정한다. revise = 메모를 반영해 다시 검수.
     */
    public function decideReview(Request $request, FeedbackPost $post): JsonResponse
    {
        abort_unless((bool) Auth::user()?->canSeeFeedbackReviews(), 403, 'AI 검수 권한이 없습니다.');

        $validated = $request->validate([
            'decision' => 'required|in:approved,hold,rejected,revise',
            'note' => 'required_if:decision,revise|nullable|string|max:1000',
        ], [
            'note.required_if' => '재검토 요청에는 메모를 입력해주세요.',
        ]);

        $review = $post->review;
        abort_unless((bool) $review, 404, '검수 내용이 없습니다.');
        abort_if($review->applied_at !== null, 409, '이미 반영된 검수입니다.');
        abort_if(in_array($post->status, ['done', 'rejected'], true), 409, '완료·반려된 글의 검수는 결정할 수 없습니다.');

        $wasApproved = $review->decision === 'approved';

        $review->update([
            'decision' => $validated['decision'],
            'decision_note' => $validated['note'] ?? null,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);

        if ($validated['decision'] === 'approved' && in_array($post->status, ['waiting', 'hold'], true)) {
            $post->update(['status' => 'reviewing']);
        } elseif ($wasApproved && $validated['decision'] !== 'approved' && $post->status === 'reviewing') {
            // 승인 철회 — 승인으로 올렸던 "검토중"을 되돌린다 (보류는 글도 보류로, 그 외는 대기로)
            $post->update(['status' => $validated['decision'] === 'hold' ? 'hold' : 'waiting']);
        }

        return response()->json([
            'ok' => true,
            'status' => $post->status,
            'review' => $review->fresh('decider:id,display_name,username')->toPayload(),
        ]);
    }

    /** AI 검수 열람자 지정 — 마스터 전용. 활성 사용자 ID 목록으로 통째 교체 (마스터는 목록과 무관하게 항상 열람) */
    public function updateReviewViewers(Request $request): JsonResponse
    {
        abort_unless($this->isDeveloper(), 403, '마스터만 AI 검수 열람자를 지정할 수 있습니다.');

        $validated = $request->validate([
            'user_ids' => 'present|array|max:100',
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)], // 비활성 계정은 지정 불가
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['user_ids'])));
        Setting::set('feedback_review_viewers', json_encode($ids));

        return response()->json(['ok' => true, 'user_ids' => $ids]);
    }

    public function updateStatus(Request $request, FeedbackPost $post): JsonResponse
    {
        abort_unless($this->isDeveloper(), 403, '개발자만 처리할 수 있습니다.');

        $validated = $request->validate([
            'status' => 'required|in:waiting,reviewing,hold,done,rejected',
            'reject_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ], [
            'reject_reason.required_if' => '반려 사유를 입력해주세요.',
        ]);

        $post->update([
            'status' => $validated['status'],
            'reject_reason' => $validated['status'] === 'rejected' ? $validated['reject_reason'] : null,
            // 완료 전환 시점 기록 — 완료 글은 완료된 순으로 정렬. 완료 해제 시 초기화
            'done_at' => $validated['status'] === 'done' ? ($post->done_at ?? now()) : null,
        ]);

        $label = FeedbackPost::STATUS_LABELS[$validated['status']];
        $this->notifyUsers(
            collect([$post->author])->filter(fn ($u) => $u && $u->id !== Auth::id()),
            new FeedbackActivity('📮 피드백 '.$label.': '.$post->title,
                $validated['status'] === 'rejected' ? '사유: '.$validated['reject_reason'] : '상태가 "'.$label.'"(으)로 변경되었습니다.',
                'feedback-status-'.$post->id)
        );

        return response()->json(['ok' => true, 'status' => $validated['status'], 'reject_reason' => $post->reject_reason]);
    }

    public function storeComment(Request $request, FeedbackPost $post): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:feedback_comments,id',
        ]);

        // 대댓글은 1뎁스만 — 같은 글의 최상위 의견에만 달 수 있음
        $parent = null;
        if (! empty($validated['parent_id'])) {
            $parent = FeedbackComment::findOrFail($validated['parent_id']);
            abort_unless($parent->feedback_post_id === $post->id && $parent->parent_id === null, 422, '답글에는 다시 답글을 달 수 없습니다.');
        }

        // 중복 전송 방지 (연타·IME Enter 이중 발화) — 10초 내 동일 내용은 기존 의견 반환
        $duplicate = $post->comments()
            ->where('user_id', Auth::id())
            ->where('body', $validated['body'])
            ->where('created_at', '>=', now()->subSeconds(10))
            ->latest('id')
            ->first();
        if ($duplicate) {
            return response()->json($this->commentPayload($duplicate->load('user:id,display_name,username,role')));
        }

        $comment = $post->comments()->create([
            'user_id' => Auth::id(),
            'parent_id' => $parent?->id,
            'body' => $validated['body'],
        ]);

        $me = Auth::user();
        $myName = $me->display_name ?? $me->username;
        $excerpt = mb_substr($validated['body'], 0, 80);

        // @멘션 호출 알림 (본인 제외)
        $mentioned = MentionService::users($validated['body'])->reject(fn ($u) => $u->id === $me->id);
        $this->notifyUsers($mentioned, new MentionAlert(
            '📣 '.$myName.'님이 회원님을 언급했습니다',
            $post->title.' — '.$excerpt,
            '/feedback',
            'feedback-mention-'.$comment->id
        ));

        // 대댓글이면 부모 의견 작성자에게 알림 (본인·멘션 수신자 제외)
        $notified = $mentioned->pluck('id')->push($me->id);
        if ($parent && $parent->user && $parent->user->is_active && ! $notified->contains($parent->user_id)) {
            $this->notifyUsers(collect([$parent->user]), new MentionAlert(
                '↩ '.$myName.'님이 내 의견에 답글을 남겼습니다',
                $post->title.' — '.$excerpt,
                '/feedback',
                'feedback-reply-'.$comment->id
            ));
            $notified->push($parent->user_id);
        }

        // 작성자 + 개발자에게 일반 알림 (본인·위에서 이미 알림 받은 사람 제외)
        $recipients = User::where('is_active', true)
            ->where(fn ($q) => $q->where('role', 'master')->orWhere('id', $post->created_by))
            ->whereNotIn('id', $notified)
            ->get();
        $this->notifyUsers($recipients, new FeedbackActivity(
            '💬 피드백 의견: '.$post->title,
            $myName.' — '.$excerpt,
            'feedback-comment-'.$post->id
        ));

        return response()->json($this->commentPayload($comment->load('user:id,display_name,username,role')), 201);
    }

    public function destroyComment(FeedbackComment $comment): JsonResponse
    {
        abort_unless($comment->user_id === Auth::id() || $this->isDeveloper(), 403);

        FeedbackComment::where('parent_id', $comment->id)->delete(); // 대댓글 함께 삭제
        $comment->delete();

        return response()->json(['ok' => true]);
    }

    public function serveAttachment(FeedbackAttachment $attachment)
    {
        abort_unless(Storage::exists($attachment->file_path), 404);

        return Storage::response($attachment->file_path, $attachment->file_name, ImageThumbnailService::cacheHeaders());
    }

    public function thumbAttachment(FeedbackAttachment $attachment, ImageThumbnailService $thumbs)
    {
        abort_unless(Storage::exists($attachment->file_path), 404);

        // 영상은 이미지 썸네일 생성 불가 — 원본 응답으로 폴백
        if (str_starts_with((string) $attachment->mime_type, 'video/')) {
            return Storage::response($attachment->file_path, $attachment->file_name, ImageThumbnailService::cacheHeaders());
        }

        return $thumbs->response($attachment->file_path, $attachment->file_name);
    }

    private function isDeveloper(): bool
    {
        return Auth::user()?->role === 'master';
    }

    /** 작성자(잠금 전) 또는 개발자만 수정·삭제 가능 */
    private function authorizeEdit(FeedbackPost $post): void
    {
        // 관리자(master/admin)는 모든 글 수정·삭제 가능 (잠금 무시)
        if (Auth::user()?->isAdmin()) {
            return;
        }
        abort_unless($post->created_by === Auth::id(), 403, '작성자만 수정할 수 있습니다.');
        abort_if($post->isLockedForAuthor(), 403, '완료/반려된 글은 수정할 수 없습니다.');
    }

    /** @return array<string, int> */
    private function statusCounts(string $type): array
    {
        $rows = FeedbackPost::where('type', $type)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return collect(array_keys(FeedbackPost::STATUS_LABELS))
            ->mapWithKeys(fn ($s) => [$s => (int) ($rows[$s] ?? 0)])
            ->all();
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
            'status_label' => FeedbackPost::STATUS_LABELS[$p->status] ?? $p->status,
            'priority' => $p->priority,
            'priority_label' => $p->priority ? (FeedbackPost::PRIORITY_LABELS[$p->priority] ?? $p->priority) : null,
            'reject_reason' => $p->reject_reason,
            'author' => $p->author?->display_name ?? $p->author?->username,
            'author_id' => $p->created_by,
            'is_mine' => $p->created_by === Auth::id(),
            'locked' => $p->isLockedForAuthor(),
            'created_at' => $p->created_at->format('n/j'),
            'done_at' => $p->done_at?->format('n/j'),
            'comments_count' => $p->comments_count ?? 0,
            'comments' => $p->relationLoaded('comments') ? $p->comments->map(fn ($c) => $this->commentPayload($c))->all() : [],
            // 열람 권한자 응답에서만 review 관계가 로드됨 (list 참고) — 그 외에는 키 자체가 null
            'review' => (Auth::user()?->canSeeFeedbackReviews() && $p->relationLoaded('review')) ? $p->review?->toPayload() : null,
            'attachments' => $p->relationLoaded('attachments') ? $p->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'is_video' => str_starts_with((string) $a->mime_type, 'video/'),
                'url' => route('feedback-attachments.serve', $a),
                'thumb_url' => route('feedback-attachments.thumb', $a).'?v=2',
            ])->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function commentPayload(FeedbackComment $c): array
    {
        return [
            'id' => $c->id,
            'parent_id' => $c->parent_id,
            'body' => $c->body,
            'user' => $c->user?->display_name ?? $c->user?->username,
            'is_dev' => $c->user?->role === 'master',
            'is_mine' => $c->user_id === Auth::id(),
            'created_at' => $c->created_at->format('n/j'),
        ];
    }

    /** 개발자 전체에게 알림 (등록자 본인 제외) */
    private function notifyDevelopers(FeedbackPost $post, string $title): void
    {
        $devs = User::where('role', 'master')->where('is_active', true)
            ->where('id', '!=', Auth::id())->get();
        $this->notifyUsers($devs, new FeedbackActivity($title, $post->page.' · '.($post->author?->display_name ?? ''), 'feedback-new-'.$post->id));
    }

    /** @param  Collection<int, User>|\Illuminate\Database\Eloquent\Collection<int, User>  $users */
    private function notifyUsers($users, Notification $notification): void
    {
        try {
            foreach ($users as $user) {
                $user->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('피드백 푸시 발송 실패: '.$e->getMessage());
        }
    }
}

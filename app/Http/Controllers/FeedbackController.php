<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileUploads;
use App\Models\FeedbackAttachment;
use App\Models\FeedbackComment;
use App\Models\FeedbackPost;
use App\Models\User;
use App\Notifications\FeedbackActivity;
use App\Services\ImageThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        ]);
    }

    /** 목록 + 탭별 처리 현황 카운트 */
    public function list(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:bug,feature',
            'status' => 'nullable|in:waiting,reviewing,hold,done,rejected',
            'page' => 'nullable|string|max:50',
            'sort' => 'nullable|in:latest,oldest',
            'q' => 'nullable|string|max:100',
        ]);

        $query = FeedbackPost::with(['author:id,display_name,username,role', 'comments.user:id,display_name,username,role', 'attachments'])
            ->withCount('comments')
            ->where('type', $validated['type']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
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

        $posts = $query->orderBy('created_at', ($validated['sort'] ?? 'latest') === 'oldest' ? 'asc' : 'desc')
            ->limit(200)
            ->get()
            ->map(fn ($p) => $this->postPayload($p));

        return response()->json([
            'posts' => $posts,
            'counts' => $this->statusCounts($validated['type']),
            'tab_totals' => [
                'bug' => FeedbackPost::where('type', 'bug')->count(),
                'feature' => FeedbackPost::where('type', 'feature')->count(),
            ],
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
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp|max:20480',
        ], [
            'page.required' => '어떤 페이지인지 선택해주세요.',
            'files.required' => '버그 제보에는 스크린샷 첨부가 필수입니다.',
            'files.*.mimes' => '이미지 파일만 첨부할 수 있습니다.',
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
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp|max:20480',
        ], [
            'files.*.mimes' => '이미지 파일만 첨부할 수 있습니다.',
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
        $validated = $request->validate(['body' => 'required|string|max:2000']);

        $comment = $post->comments()->create([
            'user_id' => Auth::id(),
            'body' => $validated['body'],
        ]);

        // 작성자 + 개발자에게 알림 (본인 제외)
        $recipients = User::where('is_active', true)
            ->where(fn ($q) => $q->where('role', 'master')->orWhere('id', $post->created_by))
            ->where('id', '!=', Auth::id())
            ->get();
        $this->notifyUsers($recipients, new FeedbackActivity(
            '💬 피드백 의견: '.$post->title,
            (Auth::user()->display_name ?? Auth::user()->username).' — '.mb_substr($validated['body'], 0, 80),
            'feedback-comment-'.$post->id
        ));

        return response()->json($this->commentPayload($comment->load('user:id,display_name,username,role')), 201);
    }

    public function destroyComment(FeedbackComment $comment): JsonResponse
    {
        abort_unless($comment->user_id === Auth::id() || $this->isDeveloper(), 403);

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
            'reject_reason' => $p->reject_reason,
            'author' => $p->author?->display_name ?? $p->author?->username,
            'author_id' => $p->created_by,
            'is_mine' => $p->created_by === Auth::id(),
            'locked' => $p->isLockedForAuthor(),
            'created_at' => $p->created_at->format('n/j'),
            'comments_count' => $p->comments_count ?? 0,
            'comments' => $p->relationLoaded('comments') ? $p->comments->map(fn ($c) => $this->commentPayload($c))->all() : [],
            'attachments' => $p->relationLoaded('attachments') ? $p->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'url' => route('feedback-attachments.serve', $a),
                'thumb_url' => route('feedback-attachments.thumb', $a),
            ])->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function commentPayload(FeedbackComment $c): array
    {
        return [
            'id' => $c->id,
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
    private function notifyUsers($users, FeedbackActivity $notification): void
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

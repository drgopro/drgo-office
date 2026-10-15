<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\NoticeAttachment;
use App\Models\NoticeCategory;
use App\Models\NoticeRead;
use App\Models\Team;
use App\Models\User;
use App\Rules\SafeAttachment;
use App\Services\ImageThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * 공지사항 게시판 — 위키에서 분리한 독립 게시판.
 * 위키의 작성/카테고리 트리/첨부/고정/임시저장/열람 제한/읽음 확인/수동 정렬 구조를 가져오되
 * 연결도·템플릿·회의록 댓글·특수 유형은 제외. 작성/수정/삭제는 관리자 전용.
 * 고정(핀)에는 유지 기한(pinned_until)이 있어 지나면 일반 글처럼 정렬된다.
 */
class NoticeController extends Controller
{
    /** 상단 고정 유효 조건 — 기한(pinned_until)이 없거나 아직 지나지 않은 고정 글 */
    private function pinnedNowOrder(): string
    {
        $today = now()->toDateString();

        return "CASE WHEN is_pinned = 1 AND (pinned_until IS NULL OR pinned_until >= '{$today}') THEN 1 ELSE 0 END";
    }

    public function index(Request $request)
    {
        $me = Auth::user();
        $query = Notice::published()->visibleTo($me)->with('creator', 'updater');

        // 검색어 — 공백으로 나눈 모든 단어가 제목 또는 본문에 있어야 한다 (단어별 AND)
        if ($search = trim((string) $request->query('search'))) {
            foreach (preg_split('/\s+/u', $search) ?: [] as $term) {
                if ($term === '') {
                    continue;
                }
                $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%"));
            }
        }

        // 기간 필터 — 수정일(기본) 또는 작성일 기준
        $dateField = $request->query('date_field') === 'created' ? 'created_at' : 'updated_at';
        if ($from = $request->query('date_from')) {
            $query->whereDate($dateField, '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate($dateField, '<=', $to);
        }

        if ($author = (int) $request->query('author')) {
            $query->where('created_by', $author);
        }

        $tree = NoticeCategory::orderBy('sort_order')->orderBy('id')->get();

        // 유효한 고정 글 우선(기한 지난 고정은 일반 취급), 그다음 작성시간 최신순
        $notices = $query->orderByRaw($this->pinnedNowOrder().' desc')->orderByDesc('created_at')->get();
        $catCounts = Notice::published()->visibleTo($me)->whereNotNull('category_id')->selectRaw('category_id, count(*) as c')
            ->groupBy('category_id')->pluck('c', 'category_id');
        $uncategorized = Notice::published()->visibleTo($me)->whereNull('category_id')->count();
        $authors = User::whereIn('id', Notice::published()->visibleTo($me)->whereNotNull('created_by')->distinct()->pluck('created_by'))
            ->orderBy('display_name')->get(['id', 'display_name']);

        return view('notices.index', compact('notices', 'tree', 'catCounts', 'uncategorized', 'authors'));
    }

    public function create()
    {
        abort_unless(Auth::user()->isAdmin(), 403, '공지사항은 관리자만 작성할 수 있습니다.');
        $tree = NoticeCategory::orderBy('sort_order')->orderBy('id')->get();
        $teams = Team::orderBy('name')->get(['id', 'name']);

        return view('notices.create', compact('tree', 'teams'));
    }

    public function show(Notice $notice)
    {
        abort_unless($notice->canView(Auth::user()), 403, '이 공지를 열람할 권한이 없습니다.');
        $notice->load('creator', 'updater');
        $tree = NoticeCategory::orderBy('sort_order')->orderBy('id')->get();
        $teams = Team::orderBy('name')->get(['id', 'name']);

        // 읽음 확인 — 문서를 열면 기록 (게스트 제외, 임시저장 제외)
        $readStatus = null;
        if (! $notice->is_draft && ! Auth::user()->isGuest()) {
            NoticeRead::firstOrCreate(
                ['notice_id' => $notice->id, 'user_id' => Auth::id()],
                ['read_at' => now()],
            );

            $staff = User::where('is_active', true)
                ->whereIn('role', ['master', 'admin', 'member'])
                ->get(['id', 'display_name', 'username', 'role', 'team_id'])
                ->filter(fn (User $u) => $notice->canView($u))
                ->values();
            $readAt = $notice->reads()->whereIn('user_id', $staff->pluck('id'))->pluck('read_at', 'user_id');
            [$readers, $unreaders] = $staff->partition(fn (User $u) => isset($readAt[$u->id]));
            $readStatus = [
                'readers' => $readers->map(fn (User $u) => [
                    'name' => $u->display_name ?? $u->username,
                    'read_at' => $readAt[$u->id]->format('m.d H:i'),
                ])->sortBy('read_at')->values()->all(),
                'unreaders' => $unreaders->map(fn (User $u) => ['name' => $u->display_name ?? $u->username])->values()->all(),
            ];
        }

        return view('notices.show', compact('notice', 'tree', 'teams', 'readStatus'));
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'title' => "{$req}|string|max:200",
            'category_id' => 'nullable|integer|exists:notice_categories,id',
            'content' => "{$req}|string",
            'is_pinned' => 'boolean',
            'pinned_until' => 'nullable|date', // 고정 유지 기한 — 비우면 무기한
            'is_draft' => ($partial ? 'sometimes|' : '').'boolean',
            'allowed_team_ids' => 'nullable|array',
            'allowed_team_ids.*' => 'integer|exists:teams,id',
        ];
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, '공지사항은 관리자만 작성할 수 있습니다.');
        $validated = $request->validate($this->rules());
        $validated['allowed_team_ids'] = array_values($validated['allowed_team_ids'] ?? []) ?: null;
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $notice = Notice::create($validated);
        $this->linkPendingAttachments($notice);

        if ($request->wantsJson()) {
            return response()->json($notice, 201);
        }

        return redirect()->route('notices.show', $notice)->with('success', '공지사항이 등록되었습니다.');
    }

    public function update(Request $request, Notice $notice)
    {
        abort_unless(Auth::user()->isAdmin(), 403, '공지사항은 관리자만 수정할 수 있습니다.');
        $validated = $request->validate($this->rules(true));
        if (array_key_exists('allowed_team_ids', $validated)) {
            $validated['allowed_team_ids'] = array_values($validated['allowed_team_ids'] ?? []) ?: null;
        }

        // 임시저장 → 발행 시점에 작성일을 현재로 (묵힌 초안이 목록 아래에 묻히지 않도록)
        if ($notice->is_draft && array_key_exists('is_draft', $validated) && ! $validated['is_draft']) {
            $notice->created_at = now();
        }

        $validated['updated_by'] = Auth::id();
        $notice->update($validated);
        $this->linkPendingAttachments($notice);

        if ($request->wantsJson()) {
            return response()->json($notice);
        }

        return redirect()->route('notices.show', $notice)->with('success', '공지사항이 수정되었습니다.');
    }

    public function destroy(Request $request, Notice $notice)
    {
        abort_unless(Auth::user()->isAdmin(), 403, '공지사항은 관리자만 삭제할 수 있습니다.');
        $notice->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notices.index')->with('success', '공지사항이 삭제되었습니다.');
    }

    /** 선택한 공지들의 카테고리 일괄 이동 — 관리자 전용 */
    public function bulkCategory(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, '공지 이동은 관리자만 가능합니다.');
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:notices,id',
            'category_id' => 'nullable|integer|exists:notice_categories,id',
        ]);

        $moved = Notice::whereIn('id', $validated['ids'])->update([
            'category_id' => $validated['category_id'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['ok' => true, 'moved' => $moved]);
    }

    /** 게시물 수동 정렬 (카테고리 내) — 관리자 전용 */
    public function reorder(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, '게시물 순서 변경은 관리자만 가능합니다.');

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:notices,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            Notice::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['ok' => true]);
    }

    /** 현재 정렬 상태 (폴링용) */
    public function orderState()
    {
        return response()->json(
            Notice::published()->get(['id', 'sort_order', 'is_pinned', 'pinned_until'])
                ->map(fn ($n) => ['id' => $n->id, 'sort_order' => $n->sort_order, 'is_pinned' => $n->isPinnedNow()])
        );
    }

    /** 내 임시저장 목록 — 작성 페이지 '불러오기' 모달용 */
    public function drafts()
    {
        return response()->json(
            Notice::where('is_draft', true)->where('created_by', Auth::id())
                ->orderByDesc('updated_at')
                ->get(['id', 'title', 'updated_at'])
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'updated_at' => $n->updated_at->format('Y.m.d H:i'),
                ])
        );
    }

    /** 임시저장 글 단건 — 이어서 작성용 (본인 것만) */
    public function draftShow(Notice $notice)
    {
        abort_unless($notice->is_draft && $notice->created_by === Auth::id(), 404);

        return response()->json([
            'id' => $notice->id,
            'title' => $notice->title,
            'category_id' => $notice->category_id,
            'content' => $notice->content,
            'is_pinned' => (bool) $notice->is_pinned,
            'pinned_until' => $notice->pinned_until?->format('Y-m-d'),
        ]);
    }

    /** 본문에 삽입된 미소속(notice_id null) 업로드를 게시물에 연결 */
    private function linkPendingAttachments(Notice $notice): void
    {
        if (! $notice->content) {
            return;
        }
        preg_match_all('#/notice-files/(\d+)#', $notice->content, $m);
        $ids = array_unique($m[1] ?? []);
        if ($ids) {
            NoticeAttachment::whereIn('id', $ids)->whereNull('notice_id')->update(['notice_id' => $notice->id]);
        }
    }

    /** 파일 업로드 (에디터에서 이미지/파일 삽입용) */
    public function uploadFile(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403, '공지사항 첨부는 관리자만 올릴 수 있습니다.');
        $request->validate([
            'file' => ['required', 'file', 'max:102400', new SafeAttachment], // 100MB
            'notice_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $path = $file->store('notices');

        $attachment = NoticeAttachment::create([
            'notice_id' => $request->notice_id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $isImage = str_starts_with($file->getMimeType(), 'image/');
        $url = route('notices.file', $attachment);

        return response()->json([
            'id' => $attachment->id,
            'url' => $url,
            'name' => $file->getClientOriginalName(),
            'is_image' => $isImage,
            'markdown' => $isImage ? "![{$file->getClientOriginalName()}]({$url})" : "[{$file->getClientOriginalName()}]({$url})",
        ]);
    }

    public function serveFile(NoticeAttachment $attachment)
    {
        if (! Storage::exists($attachment->file_path)) {
            abort(404);
        }

        return Storage::response($attachment->file_path, $attachment->file_name, ImageThumbnailService::cacheHeaders());
    }

    public function thumbFile(NoticeAttachment $attachment, ImageThumbnailService $thumbs)
    {
        abort_unless(Storage::exists($attachment->file_path), 404);

        if (! $attachment->isImage()) {
            return Storage::response($attachment->file_path, $attachment->file_name, ImageThumbnailService::cacheHeaders());
        }

        return $thumbs->response($attachment->file_path, $attachment->file_name);
    }
}

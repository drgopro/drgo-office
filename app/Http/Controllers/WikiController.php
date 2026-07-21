<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiAttachment;
use App\Models\WikiCategory;
use App\Models\WikiComment;
use App\Rules\SafeAttachment;
use App\Services\ImageThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WikiController extends Controller
{
    public function index(Request $request)
    {
        $query = Wiki::published()->with('creator', 'updater')->withCount('comments');

        // 검색어 — 그룹으로 묶어 기간/작성자 필터와 AND 유지 (mysql 외 드라이버는 like 폴백)
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                if (DB::getDriverName() === 'mysql') {
                    $q->whereFullText(['title', 'content'], $search)
                        ->orWhere('title', 'like', "%{$search}%");
                } else {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                }
            });
        }

        // 기간 필터 — 수정일(기본) 또는 작성일 기준
        $dateField = $request->query('date_field') === 'created' ? 'created_at' : 'updated_at';
        if ($from = $request->query('date_from')) {
            $query->whereDate($dateField, '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate($dateField, '<=', $to);
        }

        // 작성자 필터
        if ($author = (int) $request->query('author')) {
            $query->where('created_by', $author);
        }

        // 카테고리 트리 (계층). 카테고리 필터는 클라이언트에서 즉시 처리(새로고침 없음).
        $tree = WikiCategory::orderBy('sort_order')->orderBy('id')->get();

        // 고정 문서 우선, 그다음 작성시간 최신순 (수정해도 목록 순서 유지)
        $wikis = $query->orderByDesc('is_pinned')->orderByDesc('created_at')->get();
        $categories = Wiki::published()->select('category')->distinct()->orderBy('category')->pluck('category');
        // 카테고리별 직접 문서 수 (트리 배지용) — 공지/업데이트는 카테고리 체계와 별개
        $catCounts = Wiki::published()->whereNotNull('category_id')->selectRaw('category_id, count(*) as c')
            ->groupBy('category_id')->pluck('c', 'category_id');
        $uncategorized = Wiki::published()->whereNull('category_id')->where('type', 'normal')->count();
        // 특수 유형(공지사항/업데이트) 문서 수
        $typeCounts = Wiki::published()->whereIn('type', array_keys(Wiki::SPECIAL_TYPES))
            ->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');
        // 검색 필터 작성자 옵션 — 문서를 작성한 적 있는 사용자만
        $authors = User::whereIn('id', Wiki::published()->whereNotNull('created_by')->distinct()->pluck('created_by'))
            ->orderBy('display_name')->get(['id', 'display_name']);

        return view('wiki.index', compact('wikis', 'categories', 'tree', 'catCounts', 'uncategorized', 'typeCounts', 'authors'));
    }

    /** 카테고리 id + 모든 하위 id (flat 트리에서 계산) */
    private function descendantIds(int $rootId, $tree): array
    {
        $byParent = [];
        foreach ($tree as $c) {
            $byParent[$c->parent_id][] = $c->id;
        }
        $ids = [$rootId];
        $stack = [$rootId];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($byParent[$cur] ?? [] as $child) {
                $ids[] = $child;
                $stack[] = $child;
            }
        }

        return $ids;
    }

    public function create()
    {
        $categories = Wiki::select('category')->distinct()->orderBy('category')->pluck('category');
        $tree = WikiCategory::orderBy('sort_order')->orderBy('id')->get();

        return view('wiki.create', compact('categories', 'tree'));
    }

    /**
     * 특수 유형(공지사항/업데이트/회의록) 처리 — 카테고리 체계와 분리(카테고리 강제 해제).
     * 공지사항/업데이트는 관리자 전용, 회의록은 전 직원 작성 가능. type 미지정 시 normal 유지.
     */
    private function applySpecialType(array &$validated): void
    {
        if (empty($validated['type']) || $validated['type'] === 'normal') {
            return;
        }
        if (in_array($validated['type'], Wiki::ADMIN_ONLY_TYPES, true)) {
            abort_unless(Auth::user()->isAdmin(), 403, '공지사항/업데이트는 관리자만 작성할 수 있습니다.');
        }
        $validated['category_id'] = null;
        $validated['category'] = Wiki::SPECIAL_TYPES[$validated['type']];
        // is_pinned는 유지 — 게시판 안 목록에서도 상단 고정 가능
    }

    /** category_id가 있으면 노드명으로 category 문자열 동기화(하위 호환·풀텍스트용) */
    private function syncCategoryName(array &$validated): void
    {
        if (! empty($validated['category_id'])) {
            $node = WikiCategory::find($validated['category_id']);
            if ($node) {
                $validated['category'] = mb_substr($node->name, 0, 50);
            }
        } elseif (array_key_exists('category_id', $validated) && empty($validated['category'])) {
            $validated['category'] = '미분류';
        }
    }

    public function show(Wiki $wiki)
    {
        $wiki->load('creator', 'updater');
        if ($wiki->type === 'meeting') {
            $wiki->load('comments.user:id,display_name,username');
        }
        $tree = WikiCategory::orderBy('sort_order')->orderBy('id')->get();

        return view('wiki.show', compact('wiki', 'tree'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'category' => 'nullable|string|max:50',
            'category_id' => 'nullable|integer|exists:wiki_categories,id',
            'type' => 'nullable|in:normal,notice,update,meeting',
            'content' => 'required|string',
            'is_pinned' => 'boolean',
            'is_draft' => 'boolean', // 자동저장 = 임시저장 (목록 미노출)
        ]);

        $this->syncCategoryName($validated);
        $this->applySpecialType($validated);
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $wiki = Wiki::create($validated);
        $this->linkPendingAttachments($wiki);

        if ($request->wantsJson()) {
            return response()->json($wiki, 201);
        }

        return redirect()->route('wiki.show', $wiki)->with('success', '문서가 생성되었습니다.');
    }

    public function update(Request $request, Wiki $wiki)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'category' => 'sometimes|nullable|string|max:50',
            'category_id' => 'sometimes|nullable|integer|exists:wiki_categories,id',
            'type' => 'sometimes|in:normal,notice,update,meeting',
            'content' => 'sometimes|string',
            'is_pinned' => 'boolean',
            'is_draft' => 'sometimes|boolean',
        ]);

        // 임시저장 → 발행 시점에 작성일을 현재로 (며칠 묵힌 초안이 목록 아래에 묻히지 않도록)
        if ($wiki->is_draft && array_key_exists('is_draft', $validated) && ! $validated['is_draft']) {
            $wiki->created_at = now();
        }

        // 유형 변경 지원 — type 파라미터가 없으면 현재 유형 유지
        $newType = ($validated['type'] ?? null) ?: $wiki->type;
        // 관리자 전용 유형(공지/업데이트)은 해당 유형으로의 전환·해제·수정 모두 관리자만
        if (in_array($wiki->type, Wiki::ADMIN_ONLY_TYPES, true) || in_array($newType, Wiki::ADMIN_ONLY_TYPES, true)) {
            abort_unless(Auth::user()->isAdmin(), 403, '공지사항/업데이트는 관리자만 수정할 수 있습니다.');
        }
        if (isset(Wiki::SPECIAL_TYPES[$newType])) {
            // 특수 유형으로 유지/전환 — 카테고리 체계와 분리(카테고리 강제 해제), 고정은 유지
            $validated['type'] = $newType;
            $validated['category_id'] = null;
            $validated['category'] = Wiki::SPECIAL_TYPES[$newType];
        } else {
            // 특수 유형 해제(→일반) 시 카테고리 미지정이면 미분류로
            if ($wiki->type !== 'normal' && ! array_key_exists('category_id', $validated)) {
                $validated['category_id'] = null;
                $validated['category'] = '미분류';
            }
            $this->syncCategoryName($validated);
        }
        $validated['updated_by'] = Auth::id();
        $wiki->update($validated);
        $this->linkPendingAttachments($wiki);

        if ($request->wantsJson()) {
            return response()->json($wiki);
        }

        return redirect()->route('wiki.show', $wiki)->with('success', '문서가 수정되었습니다.');
    }

    /** 선택한 문서들의 일괄 이동 — 카테고리 또는 특수 유형(공지/업데이트/회의록) 대상 */
    public function bulkCategory(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:wikis,id',
            'category_id' => 'nullable|integer|exists:wiki_categories,id',
            'type' => 'nullable|in:notice,update,meeting',
        ]);

        $targetType = $validated['type'] ?? null;
        if (in_array($targetType, Wiki::ADMIN_ONLY_TYPES, true)) {
            abort_unless(Auth::user()->isAdmin(), 403, '공지사항/업데이트로는 관리자만 이동할 수 있습니다.');
        }

        $query = Wiki::whereIn('id', $validated['ids']);
        // 관리자 전용 유형 문서는 관리자만 이동 가능 — 일반 직원 요청에서는 제외
        if (! Auth::user()->isAdmin()) {
            $query->whereNotIn('type', Wiki::ADMIN_ONLY_TYPES);
        }

        if ($targetType) {
            // 특수 유형으로 이동 — 카테고리 체계와 분리
            $moved = $query->update([
                'type' => $targetType,
                'category_id' => null,
                'category' => Wiki::SPECIAL_TYPES[$targetType],
                'is_pinned' => false,
                'updated_by' => Auth::id(),
            ]);
        } else {
            // 카테고리(또는 미분류)로 이동 — 특수 유형 문서는 일반 문서로 전환
            $categoryId = $validated['category_id'] ?? null;
            $categoryName = $categoryId
                ? mb_substr(WikiCategory::find($categoryId)->name, 0, 50)
                : '미분류';

            $moved = $query->update([
                'type' => 'normal',
                'category_id' => $categoryId,
                'category' => $categoryName,
                'updated_by' => Auth::id(),
            ]);
        }

        return response()->json(['ok' => true, 'moved' => $moved]);
    }

    public function destroy(Request $request, Wiki $wiki)
    {
        if (in_array($wiki->type, Wiki::ADMIN_ONLY_TYPES, true)) {
            abort_unless(Auth::user()->isAdmin(), 403, '공지사항/업데이트는 관리자만 삭제할 수 있습니다.');
        }
        $wiki->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('wiki.index')->with('success', '문서가 삭제되었습니다.');
    }

    /** 댓글 작성 — 회의록(meeting) 유형 게시물에만 허용 */
    public function storeComment(Request $request, Wiki $wiki)
    {
        abort_unless($wiki->type === 'meeting', 403, '댓글은 회의록 게시물에만 작성할 수 있습니다.');

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $wiki->comments()->create([
            'user_id' => Auth::id(),
            'body' => $validated['body'],
        ]);

        return redirect()->route('wiki.show', $wiki)->with('success', '댓글이 등록되었습니다.');
    }

    /** 댓글 수정 — 작성자 본인 또는 관리자만 */
    public function updateComment(Request $request, WikiComment $comment)
    {
        abort_unless($comment->user_id === Auth::id() || Auth::user()->isAdmin(), 403, '본인이 작성한 댓글만 수정할 수 있습니다.');

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment->update(['body' => $validated['body']]);

        return redirect()->route('wiki.show', $comment->wiki)->with('success', '댓글이 수정되었습니다.');
    }

    /** 댓글 삭제 — 작성자 본인 또는 관리자만 */
    public function destroyComment(WikiComment $comment)
    {
        abort_unless($comment->user_id === Auth::id() || Auth::user()->isAdmin(), 403, '본인이 작성한 댓글만 삭제할 수 있습니다.');

        $wiki = $comment->wiki;
        $comment->delete();

        return redirect()->route('wiki.show', $wiki)->with('success', '댓글이 삭제되었습니다.');
    }

    // 연결도 데이터 저장
    public function saveDiagram(Request $request, Wiki $wiki)
    {
        $wiki->update([
            'diagram_data' => $request->input('diagram'),
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true]);
    }

    // 연결도 데이터 조회
    public function getDiagram(Wiki $wiki)
    {
        return response()->json(['diagram' => $wiki->diagram_data]);
    }

    /** 내 임시저장 목록 — 작성 페이지 '불러오기' 모달용 */
    public function drafts()
    {
        return response()->json(
            Wiki::where('is_draft', true)->where('created_by', Auth::id())
                ->orderByDesc('updated_at')
                ->get(['id', 'title', 'type', 'updated_at'])
                ->map(fn ($w) => [
                    'id' => $w->id,
                    'title' => $w->title,
                    'type' => $w->type,
                    'updated_at' => $w->updated_at->format('Y.m.d H:i'),
                ])
        );
    }

    /** 임시저장 글 단건 — 이어서 작성용 (본인 것만) */
    public function draftShow(Wiki $wiki)
    {
        abort_unless($wiki->is_draft && $wiki->created_by === Auth::id(), 404);

        return response()->json([
            'id' => $wiki->id,
            'title' => $wiki->title,
            'type' => $wiki->type,
            'category_id' => $wiki->category_id,
            'content' => $wiki->content,
            'is_pinned' => (bool) $wiki->is_pinned,
        ]);
    }

    /**
     * 본문에 삽입된 미소속(wiki_id null) 업로드를 게시물에 연결.
     * 새 글 작성 중 업로드는 wiki_id 없이 생성되므로 저장 시점에 연결해야
     * 고아 첨부 정리(attachments:prune-orphans)에서 삭제되지 않는다.
     */
    private function linkPendingAttachments(Wiki $wiki): void
    {
        if (! $wiki->content) {
            return;
        }
        preg_match_all('#/wiki-files/(\d+)#', $wiki->content, $m);
        $ids = array_unique($m[1] ?? []);
        if ($ids) {
            WikiAttachment::whereIn('id', $ids)->whereNull('wiki_id')->update(['wiki_id' => $wiki->id]);
        }
    }

    // 파일 업로드 (에디터에서 이미지/파일 삽입용)
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:102400', new SafeAttachment], // 100MB
            'wiki_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $path = $file->store('wiki');

        $attachment = WikiAttachment::create([
            'wiki_id' => $request->wiki_id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $isImage = str_starts_with($file->getMimeType(), 'image/');
        $url = route('wiki.file', $attachment);

        return response()->json([
            'id' => $attachment->id,
            'url' => $url,
            'name' => $file->getClientOriginalName(),
            'is_image' => $isImage,
            'markdown' => $isImage ? "![{$file->getClientOriginalName()}]({$url})" : "[{$file->getClientOriginalName()}]({$url})",
        ]);
    }

    // 파일 서빙
    public function serveFile(WikiAttachment $attachment)
    {
        if (! Storage::exists($attachment->file_path)) {
            abort(404);
        }

        return Storage::response($attachment->file_path, $attachment->file_name, ImageThumbnailService::cacheHeaders());
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Wiki;
use App\Models\WikiAttachment;
use App\Models\WikiCategory;
use App\Services\ImageThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WikiController extends Controller
{
    public function index(Request $request)
    {
        $query = Wiki::with('creator', 'updater');

        if ($search = $request->query('search')) {
            $query->whereFullText(['title', 'content'], $search)
                ->orWhere('title', 'like', "%{$search}%");
        }

        // 카테고리 트리 (계층). 카테고리 필터는 클라이언트에서 즉시 처리(새로고침 없음).
        $tree = WikiCategory::orderBy('sort_order')->orderBy('id')->get();

        $wikis = $query->orderByDesc('is_pinned')->orderByDesc('updated_at')->get();
        $categories = Wiki::select('category')->distinct()->orderBy('category')->pluck('category');
        // 카테고리별 직접 문서 수 (트리 배지용)
        $catCounts = Wiki::whereNotNull('category_id')->selectRaw('category_id, count(*) as c')
            ->groupBy('category_id')->pluck('c', 'category_id');
        $uncategorized = Wiki::whereNull('category_id')->count();

        return view('wiki.index', compact('wikis', 'categories', 'tree', 'catCounts', 'uncategorized'));
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
        $tree = WikiCategory::orderBy('sort_order')->orderBy('id')->get();

        return view('wiki.show', compact('wiki', 'tree'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'category' => 'nullable|string|max:50',
            'category_id' => 'nullable|integer|exists:wiki_categories,id',
            'content' => 'required|string',
            'is_pinned' => 'boolean',
        ]);

        $this->syncCategoryName($validated);
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $wiki = Wiki::create($validated);

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
            'content' => 'sometimes|string',
            'is_pinned' => 'boolean',
        ]);

        $this->syncCategoryName($validated);
        $validated['updated_by'] = Auth::id();
        $wiki->update($validated);

        if ($request->wantsJson()) {
            return response()->json($wiki);
        }

        return redirect()->route('wiki.show', $wiki)->with('success', '문서가 수정되었습니다.');
    }

    /** 선택한 문서들의 카테고리 일괄 이동 */
    public function bulkCategory(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:wikis,id',
            'category_id' => 'nullable|integer|exists:wiki_categories,id',
        ]);

        $categoryId = $validated['category_id'] ?? null;
        $categoryName = $categoryId
            ? mb_substr(WikiCategory::find($categoryId)->name, 0, 50)
            : '미분류';

        $moved = Wiki::whereIn('id', $validated['ids'])->update([
            'category_id' => $categoryId,
            'category' => $categoryName,
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['ok' => true, 'moved' => $moved]);
    }

    public function destroy(Request $request, Wiki $wiki)
    {
        $wiki->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('wiki.index')->with('success', '문서가 삭제되었습니다.');
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

    // 파일 업로드 (에디터에서 이미지/파일 삽입용)
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB
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

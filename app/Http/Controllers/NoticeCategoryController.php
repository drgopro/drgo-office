<?php

namespace App\Http\Controllers;

use App\Models\NoticeCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeCategoryController extends Controller
{
    private const MAX_DEPTH = 5;

    /** 전체 카테고리(트리 구성용 flat 목록) */
    public function index(): JsonResponse
    {
        return response()->json(
            NoticeCategory::orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id', 'name', 'sort_order'])
        );
    }

    /** 카테고리 추가 — parent_id 있으면 하위, 없으면 최상위. 총 5단계 제한. */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|integer|exists:notice_categories,id',
        ]);

        if (! empty($v['parent_id'])) {
            $parent = NoticeCategory::find($v['parent_id']);
            if ($parent && $parent->depth() >= self::MAX_DEPTH) {
                return response()->json(['message' => '카테고리는 최대 '.self::MAX_DEPTH.'단계까지만 추가할 수 있습니다.'], 422);
            }
        }

        $order = (int) NoticeCategory::where('parent_id', $v['parent_id'] ?? null)->max('sort_order') + 1;
        $cat = NoticeCategory::create([
            'name' => trim($v['name']),
            'parent_id' => $v['parent_id'] ?? null,
            'sort_order' => $order,
        ]);

        return response()->json($cat->only('id', 'parent_id', 'name', 'sort_order'), 201);
    }

    /** 이름 변경 */
    public function update(Request $request, NoticeCategory $category): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:100']);
        $category->update(['name' => trim($v['name'])]);

        return response()->json(['ok' => true, 'name' => $category->name]);
    }

    /** 형제 순서 변경 (드래그) — items: [{id, sort_order}] */
    public function reorder(Request $request): JsonResponse
    {
        $v = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:notice_categories,id',
            'items.*.sort_order' => 'required|integer',
        ]);
        foreach ($v['items'] as $it) {
            NoticeCategory::where('id', $it['id'])->update(['sort_order' => $it['sort_order']]);
        }

        return response()->json(['ok' => true]);
    }

    /** 삭제 — 하위는 부모로 끌어올리고, 연결된 공지는 부모(또는 미분류)로 이동 */
    public function destroy(NoticeCategory $category): JsonResponse
    {
        $parentId = $category->parent_id;
        NoticeCategory::where('parent_id', $category->id)->update(['parent_id' => $parentId]);
        $category->notices()->update(['category_id' => $parentId]);
        $category->delete();

        return response()->json(['ok' => true]);
    }
}

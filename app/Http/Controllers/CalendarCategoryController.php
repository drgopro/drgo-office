<?php

namespace App\Http\Controllers;

use App\Models\CalendarCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            CalendarCategory::orderBy('sort_order')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'nullable|string|max:30|regex:/^[a-z0-9_]+$/|unique:calendar_categories,key',
            'label' => 'required|string|max:50',
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => 'sometimes|boolean',
        ]);

        // key 자동 생성 (label에서 slug, 영문/숫자만)
        if (empty($validated['key'])) {
            $base = Str::slug($validated['label'], '_');
            $base = preg_replace('/[^a-z0-9_]/', '', $base);
            if (! $base) {
                $base = 'cat_'.time();
            }
            $key = $base;
            $i = 1;
            while (CalendarCategory::where('key', $key)->exists()) {
                $key = $base.'_'.$i++;
            }
            $validated['key'] = $key;
        }

        $validated['sort_order'] = (CalendarCategory::max('sort_order') ?? 0) + 1;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $category = CalendarCategory::create($validated);

        return response()->json($category, 201);
    }

    public function update(Request $request, CalendarCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:50',
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => 'sometimes|boolean',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    /** 전체 카테고리 순서 일괄 변경 (id 배열 순서대로 sort_order 재부여) */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array|min:1',
            'order.*' => 'integer|exists:calendar_categories,id',
        ]);

        foreach ($validated['order'] as $i => $id) {
            CalendarCategory::where('id', $id)->update(['sort_order' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(CalendarCategory $category): JsonResponse
    {
        // 기본 카테고리(DEFAULTS 정의된 key)는 삭제 불가 — 기존 일정에 영향
        if (array_key_exists($category->key, CalendarCategory::DEFAULTS)) {
            return response()->json([
                'message' => '기본 카테고리는 삭제할 수 없습니다. 비활성화로 사용해 주세요.',
            ], 422);
        }

        $category->delete();

        return response()->json(['ok' => true]);
    }

    public function reset(CalendarCategory $category): JsonResponse
    {
        $defaults = CalendarCategory::DEFAULTS[$category->key] ?? null;
        if (! $defaults) {
            return response()->json(['error' => '기본값 정의가 없습니다.'], 422);
        }

        $category->update([
            'label' => $defaults['label'],
            'color' => $defaults['color'],
            'text_color' => $defaults['text_color'],
            'is_active' => true,
        ]);

        return response()->json($category);
    }
}

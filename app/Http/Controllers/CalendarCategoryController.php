<?php

namespace App\Http\Controllers;

use App\Models\CalendarCategory;
use Illuminate\Http\Request;

class CalendarCategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            CalendarCategory::orderBy('sort_order')->get()
        );
    }

    public function update(Request $request, CalendarCategory $category)
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

    public function reset(CalendarCategory $category)
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

<?php

namespace App\Http\Controllers;

use App\Models\RequestItemPreset;
use Illuminate\Http\Request;

class RequestItemPresetController extends Controller
{
    /** 캘린더 모달 선택지 로딩용 — 활성 프리셋 전체 */
    public function index()
    {
        return response()->json(
            RequestItemPreset::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validatePreset($request);
        $validated['sort_order'] = $validated['sort_order']
            ?? ((int) RequestItemPreset::max('sort_order') + 1);

        return response()->json(RequestItemPreset::create($validated), 201);
    }

    public function update(Request $request, RequestItemPreset $preset)
    {
        $preset->update($this->validatePreset($request));

        return response()->json($preset);
    }

    public function destroy(RequestItemPreset $preset)
    {
        $preset->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * children = {"2뎁스 분류": ["3뎁스 항목", ...], ...}
     *
     * @return array<string, mixed>
     */
    private function validatePreset(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:100',
            'children' => 'nullable|array',
            'children.*' => 'array',
            'children.*.*' => 'string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);
    }
}

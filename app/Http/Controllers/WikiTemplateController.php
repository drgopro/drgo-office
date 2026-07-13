<?php

namespace App\Http\Controllers;

use App\Models\WikiTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WikiTemplateController extends Controller
{
    /** 템플릿 목록 (서식 내용 제외 — 불러오기 시 show로 조회) */
    public function index(): JsonResponse
    {
        return response()->json(
            WikiTemplate::with('creator:id,display_name')
                ->orderBy('name')
                ->get(['id', 'name', 'created_by', 'updated_at'])
                ->map(fn (WikiTemplate $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'creator' => $t->creator?->display_name,
                    'updated_at' => $t->updated_at?->format('Y.m.d'),
                ])
        );
    }

    /** 템플릿 단건 조회 (서식 내용 포함) */
    public function show(WikiTemplate $template): JsonResponse
    {
        return response()->json($template->only('id', 'name', 'content'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'content' => 'required|string',
        ]);

        $template = WikiTemplate::create([
            'name' => trim($validated['name']),
            'content' => $validated['content'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json($template->only('id', 'name'), 201);
    }

    /** 이름 변경 또는 서식 덮어쓰기 */
    public function update(Request $request, WikiTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'content' => 'sometimes|string',
        ]);

        if (isset($validated['name'])) {
            $validated['name'] = trim($validated['name']);
        }
        $validated['updated_by'] = Auth::id();
        $template->update($validated);

        return response()->json($template->only('id', 'name'));
    }

    public function destroy(WikiTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['ok' => true]);
    }
}

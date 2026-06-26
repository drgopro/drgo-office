<?php

namespace App\Http\Controllers;

use App\Models\ProjectSubtag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectTagController extends Controller
{
    /** 대분류(고정) + 소분류(DB) 목록 */
    public function index(): JsonResponse
    {
        return response()->json([
            'major' => config('crm.major_tags', []),
            'minor' => ProjectSubtag::orderBy('sort_order')->orderBy('id')->pluck('name')->all(),
            'can_manage' => (bool) Auth::user()?->hasPermission('tags.manage'),
        ]);
    }

    /** 소분류 추가 (권한: tags.manage) */
    public function storeSubtag(Request $request): JsonResponse
    {
        if (! Auth::user()?->hasPermission('tags.manage')) {
            return response()->json(['message' => '태그 수정 권한이 없습니다.'], 403);
        }
        $v = $request->validate(['name' => 'required|string|max:60']);
        $name = trim($v['name']);

        // 이미 있으면 기존 태그 반환(중복 INSERT 방지), 없으면 생성
        $tag = ProjectSubtag::firstOrCreate(
            ['name' => $name],
            ['sort_order' => (int) ProjectSubtag::max('sort_order') + 1]
        );

        return response()->json(['id' => $tag->id, 'name' => $tag->name], 201);
    }

    /** 소분류 삭제 (권한: tags.manage) */
    public function destroySubtag(ProjectSubtag $subtag): JsonResponse
    {
        if (! Auth::user()?->hasPermission('tags.manage')) {
            return response()->json(['message' => '태그 수정 권한이 없습니다.'], 403);
        }
        $subtag->delete();

        return response()->json(['ok' => true]);
    }
}

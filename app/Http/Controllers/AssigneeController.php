<?php

namespace App\Http\Controllers;

use App\Models\Assignee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssigneeController extends Controller
{
    public function index(): JsonResponse
    {
        // master/admin/member 역할의 활성 사용자를 Assignee에 자동 등록
        $users = User::where('is_active', true)
            ->whereIn('role', ['master', 'admin', 'member'])
            ->get();

        foreach ($users as $user) {
            Assignee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->display_name ?? $user->username,
                    'is_active' => true,
                    'display_order' => 0,
                ]
            );
        }

        // 시스템 사용자가 비활성/삭제되면 그에 연결된 Assignee도 비활성화 (외부 Assignee는 손대지 않음)
        $activeUserIds = $users->pluck('id')->toArray();
        Assignee::whereNotNull('user_id')
            ->whereNotIn('user_id', $activeUserIds)
            ->update(['is_active' => false]);

        // 활성 Assignee 반환 (외부 담당자 포함) — user_id 포함(본인 식별용)
        $assignees = Assignee::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'user_id']);

        return response()->json($assignees);
    }

    /**
     * 관리자 페이지용 — 외부/내부 모든 담당자 풀 조회 (비활성 포함)
     */
    public function manage(): JsonResponse
    {
        $assignees = Assignee::with('user:id,display_name,username,role,is_active')
            ->orderByDesc('is_active')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'display_order' => $a->display_order,
                'is_active' => (bool) $a->is_active,
                'user_id' => $a->user_id,
                'is_external' => $a->user_id === null,
                'user' => $a->user ? [
                    'id' => $a->user->id,
                    'display_name' => $a->user->display_name,
                    'role' => $a->user->role,
                    'is_active' => (bool) $a->user->is_active,
                ] : null,
            ]);

        return response()->json($assignees);
    }

    /**
     * 외부 담당자 추가 (시스템 사용자가 아닌 프리랜서/협력사 등)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:assignees,name',
            'display_order' => 'nullable|integer',
        ]);

        $assignee = Assignee::create([
            'name' => $validated['name'],
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => true,
            'user_id' => null, // 외부
        ]);

        return response()->json($assignee, 201);
    }

    /**
     * 담당자 이름/순서/활성 여부 수정.
     * 내부(user_id 있음) 담당자의 name은 사용자 display_name과 동기화되므로 수정 차단.
     */
    public function update(Request $request, Assignee $assignee): JsonResponse
    {
        if ($assignee->user_id !== null) {
            $validated = $request->validate([
                'display_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:50|unique:assignees,name,'.$assignee->id,
                'display_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);
        }

        $assignee->update($validated);

        return response()->json($assignee);
    }

    /**
     * 담당자 삭제 — 외부 담당자만 허용 (내부는 사용자 비활성으로 처리)
     */
    public function destroy(Assignee $assignee): JsonResponse
    {
        if ($assignee->user_id !== null) {
            return response()->json([
                'message' => '시스템 사용자와 연결된 담당자는 삭제할 수 없습니다. 사용자 계정을 비활성화하세요.',
            ], 422);
        }

        $assignee->delete();

        return response()->json(['ok' => true]);
    }
}

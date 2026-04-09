<?php

namespace App\Http\Controllers;

use App\Models\Assignee;
use App\Models\User;

class AssigneeController extends Controller
{
    public function index()
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

        // 비활성 사용자의 Assignee도 비활성화
        $activeUserIds = $users->pluck('id')->toArray();
        Assignee::whereNotNull('user_id')
            ->whereNotIn('user_id', $activeUserIds)
            ->update(['is_active' => false]);

        // 활성 Assignee 반환
        $assignees = Assignee::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($assignees);
    }
}

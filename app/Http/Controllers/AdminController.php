<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index()
    {
        $logs = LoginLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $sellerSettings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]);

        return view('admin.index', compact('logs', 'sellerSettings'));
    }

    public function settings()
    {
        return response()->json(Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]));
    }

    public function updateSettings(Request $request)
    {
        $keys = ['seller_name', 'seller_biz_no', 'seller_address', 'seller_biz_type', 'seller_biz_item', 'seller_phone'];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        return response()->json(['message' => '저장되었습니다.']);
    }

    // ── 사용자 관리 ──

    public function users()
    {
        $users = User::with('team')
            ->orderBy('display_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'display_name' => $u->display_name,
                'email' => $u->email,
                'role' => $u->role,
                'team_id' => $u->team_id,
                'team_name' => $u->team?->name,
                'is_active' => $u->is_active,
            ]);

        return response()->json($users);
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'display_name' => 'required|string|max:50',
            'password' => 'required|string|min:8',
            'role' => 'required|in:master,admin,member,guest',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        // admin은 master 역할 부여 불가
        if (Auth::user()->role !== 'master' && $validated['role'] === 'master') {
            return response()->json(['message' => 'master 역할은 최고관리자만 부여할 수 있습니다.'], 403);
        }

        if ($validated['role'] !== 'member') {
            $validated['team_id'] = null;
        }

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:master,admin,member,guest',
            'team_id' => 'nullable|exists:teams,id',
            'is_active' => 'boolean',
        ]);

        // admin은 master 역할 부여 불가
        if (Auth::user()->role !== 'master' && $validated['role'] === 'master') {
            return response()->json(['message' => 'master 역할은 최고관리자만 부여할 수 있습니다.'], 403);
        }

        // master 사용자는 master만 수정 가능
        if ($user->role === 'master' && Auth::user()->role !== 'master') {
            return response()->json(['message' => '최고관리자 계정은 수정할 수 없습니다.'], 403);
        }

        // member가 아니면 team_id 제거
        if ($validated['role'] !== 'member') {
            $validated['team_id'] = null;
        }

        $user->update($validated);

        return response()->json(['message' => '저장되었습니다.']);
    }

    // ── 팀 관리 ──

    public function teams()
    {
        return response()->json(Team::withCount('users')->orderBy('name')->get());
    }

    public function storeTeam(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:teams,name',
            'permissions' => 'required|array',
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'permissions' => $validated['permissions'],
        ]);

        return response()->json($team, 201);
    }

    public function updateTeam(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:50|unique:teams,name,'.$team->id,
            'permissions' => 'sometimes|array',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $team->update($validated);

        return response()->json($team);
    }

    public function destroyTeam(Team $team)
    {
        // 소속 사용자의 team_id를 null로 설정 (FK nullOnDelete이 처리하지만 명시적으로)
        $team->users()->update(['team_id' => null]);
        $team->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }
}

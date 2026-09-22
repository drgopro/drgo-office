<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * 서버 디스크 사용량 — 관리 페이지 '서버 상태' 탭.
     * 폴더별 사용량(du)은 파일이 많으면 느려 5분 캐시, ?refresh=1로 강제 갱신.
     *
     * @return JsonResponse array{total:int, free:int, used:int, used_percent:int, dirs:array<int, array{name:string, label:string, size:?int}>, checked_at:string}
     */
    public function serverStatus(Request $request)
    {
        if ($request->boolean('refresh')) {
            Cache::forget('admin.server-status');
        }

        return response()->json(Cache::remember('admin.server-status', 300, function () {
            $path = base_path();
            $total = (int) (@disk_total_space($path) ?: 0);
            $free = (int) (@disk_free_space($path) ?: 0);
            $used = max(0, $total - $free);

            // 저장 폴더별 사용량 — storage/app 하위 + 로그 + 프레임워크 캐시
            $labels = [
                'backups' => 'DB 백업', 'thumbs' => '썸네일 캐시', 'schedules' => '캘린더 첨부',
                'projects' => '프로젝트 첨부', 'clients' => '의뢰자 문서', 'feedback' => '피드백 첨부',
                'wiki' => '위키 첨부', 'estimates' => '견적서 파일', 'public' => '공개 파일',
                'private' => '비공개 파일', 'livewire-tmp' => '업로드 임시', 'logs' => '로그',
                'framework' => '프레임워크 캐시', 'seller' => '판매처 설정 파일',
            ];
            $targets = collect(glob(storage_path('app/*'), GLOB_ONLYDIR) ?: [])
                ->push(storage_path('logs'), storage_path('framework'))
                ->filter(fn ($d) => is_dir($d))->unique()->values();

            $sizes = [];
            if ($targets->isNotEmpty()) {
                try {
                    // du 한 번에 조회 (리눅스) — 실패/타임아웃 시 크기 미표시로 폴백
                    $result = Process::timeout(20)->run(array_merge(['du', '-sb', '--'], $targets->all()));
                    if ($result->successful()) {
                        foreach (explode("\n", trim($result->output())) as $line) {
                            if (preg_match('/^(\d+)\s+(.+)$/', trim($line), $m)) {
                                $sizes[$m[2]] = (int) $m[1];
                            }
                        }
                    }
                } catch (\Throwable) {
                    // du 미지원 환경 — 크기 없이 폴더 목록만
                }
            }

            $dirs = $targets->map(fn ($dir) => [
                'name' => basename($dir),
                'label' => $labels[basename($dir)] ?? basename($dir),
                'size' => $sizes[$dir] ?? null,
            ])->sortByDesc(fn ($d) => $d['size'] ?? -1)->values()->all();

            return [
                'total' => $total,
                'free' => $free,
                'used' => $used,
                'used_percent' => $total > 0 ? (int) round($used / $total * 100) : 0,
                'dirs' => $dirs,
                'checked_at' => now()->format('Y-m-d H:i'),
            ];
        }));
    }

    public function index()
    {
        $logs = LoginLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $sellerSettings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
            'seller_stamp_path', 'calendar_visit_options', 'project_cancel_reasons',
        ]);

        return view('admin.index', compact('logs', 'sellerSettings'));
    }

    public function settings()
    {
        return response()->json(Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
            'calendar_visit_options', 'project_cancel_reasons',
        ]));
    }

    public function updateSettings(Request $request)
    {
        $keys = ['seller_name', 'seller_biz_no', 'seller_address', 'seller_biz_type', 'seller_biz_item', 'seller_phone', 'calendar_visit_options', 'project_cancel_reasons'];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        return response()->json(['message' => '저장되었습니다.']);
    }

    /** 직인 이미지 업로드 — 견적서 판매처 영역 배경으로 표시 */
    public function uploadSellerStamp(Request $request)
    {
        $request->validate(
            ['stamp' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048'],
            [],
            ['stamp' => '직인 이미지'],
        );

        $old = Setting::get('seller_stamp_path');
        $path = $request->file('stamp')->store('stamps');
        if (! $path) {
            return response()->json(['message' => '직인 저장에 실패했습니다.'], 500);
        }

        Setting::set('seller_stamp_path', $path);
        if ($old && $old !== $path) {
            Storage::delete($old);
        }

        return response()->json(['message' => '직인이 등록되었습니다.']);
    }

    public function deleteSellerStamp()
    {
        if ($old = Setting::get('seller_stamp_path')) {
            Storage::delete($old);
        }
        Setting::set('seller_stamp_path', null);

        return response()->json(['message' => '직인이 삭제되었습니다.']);
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

    // Master 전용: 계정 정보 수정
    public function updateUserAccount(Request $request, User $user)
    {
        $validated = $request->validate([
            'username' => 'sometimes|string|max:50|unique:users,username,'.$user->id,
            'display_name' => 'sometimes|string|max:50',
            'email' => 'nullable|email|max:100',
            'password' => 'nullable|string|min:8',
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json(['message' => '계정 정보가 수정되었습니다.']);
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
            'permissions' => 'nullable|array',
        ]);
        $validated['permissions'] = $validated['permissions'] ?? [];

        $slug = Str::slug($validated['name']);
        if (! $slug) {
            $slug = 'team-'.time();
        }

        $team = Team::create([
            'name' => $validated['name'],
            'slug' => $slug,
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
            $slug = Str::slug($validated['name']);
            $validated['slug'] = $slug ?: 'team-'.$team->id;
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

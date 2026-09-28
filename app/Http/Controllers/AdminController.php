<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Services\ChannelTalkClient;
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
            'payment_alert_group', 'payment_alert_managers',
            'post_alert_group', 'free_post_alert_managers', 'wiki_post_alert_group', 'wiki_post_alert_managers', 'calendar_alert_group',
        ]);

        return view('admin.index', compact('logs', 'sellerSettings'));
    }

    public function settings()
    {
        return response()->json(Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
            'calendar_visit_options', 'project_cancel_reasons',
            'payment_alert_group', 'payment_alert_managers',
            'post_alert_group', 'free_post_alert_managers', 'wiki_post_alert_group', 'wiki_post_alert_managers', 'calendar_alert_group',
        ]));
    }

    public function updateSettings(Request $request)
    {
        $keys = ['seller_name', 'seller_biz_no', 'seller_address', 'seller_biz_type', 'seller_biz_item', 'seller_phone', 'calendar_visit_options', 'project_cancel_reasons', 'payment_alert_group', 'payment_alert_managers', 'post_alert_group', 'free_post_alert_managers', 'wiki_post_alert_group', 'wiki_post_alert_managers', 'calendar_alert_group'];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        return response()->json(['message' => '저장되었습니다.']);
    }

    /**
     * 결제완료 알림 테스트 발송 — 설정된 채널톡 톡방으로 담당자 멘션 포함 테스트 메시지.
     * 톡방 이름 오타/미생성/봇 미초대를 저장 즉시 확인할 수 있게 한다.
     */
    public function paymentAlertTest(ChannelTalkClient $channelTalk)
    {
        $group = trim((string) Setting::get('payment_alert_group', ''));
        if ($group === '') {
            return response()->json(['message' => '결제완료 톡방이 설정되지 않았습니다. 먼저 톡방 이름을 저장하세요.'], 422);
        }

        $managerIds = json_decode((string) Setting::get('payment_alert_managers', '[]'), true);
        $mentions = User::whereIn('id', is_array($managerIds) ? $managerIds : [])
            ->get()
            ->map(fn (User $u) => $channelTalk->managerMention($u->email, $u->display_name))
            ->implode(' ');

        $res = $channelTalk->sendGroupMessage(
            '[테스트] 결제완료 알림 연결 확인 — 이 메시지가 보이면 설정이 완료된 것입니다.'
            .($mentions !== '' ? "\n".$mentions : ''),
            $group
        );

        return ($res['ok'] ?? false)
            ? response()->json(['message' => '테스트 메시지를 보냈습니다. 채널톡 톡방을 확인하세요.'])
            : response()->json(['message' => $this->paymentAlertFailureMessage($channelTalk, $group, $res['error'] ?? '알 수 없는 오류')], 502);
    }

    /**
     * 게시물 알림 테스트 발송 — 새게시물알림 톡방 연결 확인 (Free 게시판 담당자 멘션 포함).
     */
    public function postAlertTest(ChannelTalkClient $channelTalk)
    {
        $group = trim((string) Setting::get('post_alert_group', ''));
        if ($group === '') {
            return response()->json(['message' => '게시물 알림 톡방이 설정되지 않았습니다. 먼저 톡방 이름을 저장하세요.'], 422);
        }

        $managerIds = json_decode((string) Setting::get('free_post_alert_managers', '[]'), true);
        $mentions = User::whereIn('id', is_array($managerIds) ? $managerIds : [])
            ->get()
            ->map(fn (User $u) => $channelTalk->managerMention($u->email, $u->display_name))
            ->implode(' ');

        $res = $channelTalk->sendGroupMessage(
            '[테스트] 게시물 알림 연결 확인 — drgo.pro 게시판·위키 새 글이 이 방으로 옵니다.'
            .($mentions !== '' ? "\nFree 게시판 담당자: ".$mentions : ''),
            $group
        );

        return ($res['ok'] ?? false)
            ? response()->json(['message' => '테스트 메시지를 보냈습니다. 채널톡 톡방을 확인하세요.'])
            : response()->json(['message' => $this->paymentAlertFailureMessage($channelTalk, $group, $res['error'] ?? '알 수 없는 오류')], 502);
    }

    /**
     * 캘린더 알림 테스트 발송 — 담당자 지정 알림·D-2 다이제스트 톡방 연결 확인.
     * 비어 있으면 기본 팀챗 그룹(.env)으로 발송해 기존 동작을 확인시킨다.
     */
    /**
     * 위키 알림 테스트 발송 — 위키 전용 톡방(비우면 게시물 알림 톡방) 연결 확인, 담당자 멘션 포함.
     */
    public function wikiAlertTest(ChannelTalkClient $channelTalk)
    {
        $group = trim((string) Setting::get('wiki_post_alert_group', ''))
            ?: trim((string) Setting::get('post_alert_group', ''));
        if ($group === '') {
            return response()->json(['message' => '위키 알림 톡방이 설정되지 않았습니다. 위키 톡방 또는 게시물 알림 톡방을 먼저 저장하세요.'], 422);
        }

        $managerIds = json_decode((string) Setting::get('wiki_post_alert_managers', '[]'), true);
        $mentions = User::whereIn('id', is_array($managerIds) ? $managerIds : [])
            ->get()
            ->map(fn (User $u) => $channelTalk->managerMention($u->email, $u->display_name))
            ->implode(' ');

        $res = $channelTalk->sendGroupMessage(
            '[테스트] 위키 알림 연결 확인 — 위키 새 글이 이 방으로 옵니다.'
            .($mentions !== '' ? "\n위키 담당자: ".$mentions : ''),
            $group
        );

        return ($res['ok'] ?? false)
            ? response()->json(['message' => "테스트 메시지를 '{$group}' 톡방으로 보냈습니다."])
            : response()->json(['message' => $this->paymentAlertFailureMessage($channelTalk, $group, $res['error'] ?? '알 수 없는 오류')], 502);
    }

    public function calendarAlertTest(ChannelTalkClient $channelTalk)
    {
        $group = trim((string) Setting::get('calendar_alert_group', ''));

        $res = $channelTalk->sendGroupMessage(
            '[테스트] 캘린더 알림 연결 확인 — 담당자 지정 알림과 매일 오전 9시 D-2 일정 알림이 이 방으로 옵니다.',
            $group !== '' ? $group : null
        );

        return ($res['ok'] ?? false)
            ? response()->json(['message' => '테스트 메시지를 보냈습니다. '.($group !== '' ? "'{$group}'" : '기본 팀챗').' 톡방을 확인하세요.'])
            : response()->json(['message' => $this->paymentAlertFailureMessage($channelTalk, $group ?: '(기본 그룹)', $res['error'] ?? '알 수 없는 오류')], 502);
    }

    /**
     * 테스트 발송 실패 안내 — 채널톡에서 실제로 보이는 그룹 목록을 덧붙여
     * 이름 오타/비공개 그룹(API 발송 불가) 문제를 바로 확인할 수 있게 한다.
     */
    private function paymentAlertFailureMessage(ChannelTalkClient $channelTalk, string $group, string $error): string
    {
        $message = '발송 실패: '.$error;

        $list = $channelTalk->listGroups();
        if ($list['ok'] ?? false) {
            $names = collect($list['groups'])->pluck('name')->filter()->values();
            if ($names->contains($group)) {
                $message .= " — 그룹 '{$group}'은(는) 존재합니다. 봇 발송 권한/설정을 확인하세요.";
            } elseif ($names->isNotEmpty()) {
                $message .= ' — 채널톡 API에서 보이는 그룹: '.$names->implode(', ')
                    ."\n'{$group}'이(가) 목록에 없다면 비공개 그룹입니다. 그룹을 공개로 바꾸거나 목록의 이름으로 저장하세요.";
            } else {
                $message .= ' — 채널톡 API에서 보이는 공개 그룹이 없습니다. 그룹을 공개로 만들어야 API 발송이 가능합니다.';
            }
        }

        return $message;
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

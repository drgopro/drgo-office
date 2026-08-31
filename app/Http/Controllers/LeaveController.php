<?php

namespace App\Http\Controllers;

use App\Models\LeaveGrant;
use App\Models\LeaveUsage;
use App\Models\User;
use App\Services\LeaveLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 연차 관리 — 직원은 본인 연차만 조회(/leave), 경영지원팀(leave.manage)은
 * 입사일/연도별 부여/사용 기록을 관리(/leave/manage)한다.
 */
class LeaveController extends Controller
{
    /** 내 연차 — 본인 요약 + 사용 내역 (guest 제외 전 직원) */
    public function index(Request $request)
    {
        abort_if(Auth::user()->isGuest(), 403);
        $year = (int) $request->query('year', now()->year);

        return view('leave.index', [
            'year' => $year,
            'summary' => $this->summaryFor(Auth::user(), $year),
            'canManage' => Auth::user()->canManageLeave(),
        ]);
    }

    /** 연차 관리 — 직원 목록 (leave.manage) */
    public function manage(Request $request)
    {
        $year = (int) $request->query('year', now()->year);
        $rows = User::whereIn('role', ['master', 'admin', 'member'])
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get()
            ->map(fn (User $u) => $this->summaryFor($u, $year));

        return view('leave.manage', ['year' => $year, 'rows' => $rows]);
    }

    /** 입사일·연차 기산 방식 저장 */
    public function setHireDate(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'hire_date' => 'nullable|date',
            'fiscal_leave' => 'nullable|boolean', // 체크 시 회계연도(1/1) 기준
        ]);
        $user->forceFill([
            'hire_date' => $validated['hire_date'] ?? null,
            ...($request->has('fiscal_leave') ? ['fiscal_leave' => $request->boolean('fiscal_leave')] : []),
        ])->save();

        return response()->json(['message' => '저장되었습니다.']);
    }

    /** 연도별 부여 일수 저장 (0.5 단위) */
    public function setGrant(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'days' => 'required|numeric|min:0|max:60',
            'note' => 'nullable|string|max:300',
        ]);
        LeaveGrant::updateOrCreate(
            ['user_id' => $user->id, 'year' => (int) $validated['year']],
            ['days' => round($validated['days'] * 2) / 2, 'note' => $validated['note'] ?? null, 'updated_by' => Auth::id()],
        );

        return response()->json(['message' => '저장되었습니다.']);
    }

    /** 수동 사용 기록 추가 (공휴일 보정·과거 소급 등) */
    public function addUsage(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'used_on' => 'required|date',
            'days' => 'required|numeric|in:0.5,1',
            'type' => 'required|string|max:30',
            'note' => 'nullable|string|max:300',
        ]);
        LeaveUsage::create([
            'user_id' => $user->id,
            'used_on' => $validated['used_on'],
            'days' => (float) $validated['days'],
            'type' => $validated['type'],
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['message' => '추가되었습니다.']);
    }

    /** 수동 사용 기록 삭제 — 캘린더 연동 기록은 일정에서 차감을 해제해야 한다 */
    public function deleteUsage(LeaveUsage $usage): JsonResponse
    {
        if ($usage->schedule_id) {
            return response()->json(['message' => '캘린더 연동 기록입니다. 해당 휴가 일정에서 연차 차감을 해제하거나 일정을 삭제해주세요.'], 422);
        }
        $usage->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }

    /**
     * 사용자 1명의 연도 요약 — 부여/사용/잔여 + 사용 내역.
     *
     * @return array<string, mixed>
     */
    private function summaryFor(User $user, int $year): array
    {
        $grant = LeaveGrant::where('user_id', $user->id)->where('year', $year)->first();
        $usages = LeaveUsage::where('user_id', $user->id)
            ->whereBetween('used_on', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"])
            ->orderByDesc('used_on')->orderByDesc('id')
            ->get();
        $used = (float) $usages->sum('days');
        $granted = $grant ? (float) $grant->days : null;

        return [
            'user_id' => $user->id,
            'name' => $user->display_name,
            'hire_date' => $user->hire_date?->format('Y-m-d'),
            'fiscal_leave' => (bool) $user->fiscal_leave,
            'granted' => $granted,
            'grant_note' => $grant?->note,
            'used' => $used,
            'remaining' => $granted !== null ? round($granted - $used, 1) : null,
            'suggest' => LeaveLedger::suggestGrant($user->hire_date?->format('Y-m-d'), $year, (bool) $user->fiscal_leave),
            'usages' => $usages->map(fn (LeaveUsage $u) => [
                'id' => $u->id,
                'used_on' => $u->used_on->format('Y-m-d'),
                'days' => (float) $u->days,
                'type' => $u->type,
                'note' => $u->note,
                'from_calendar' => (bool) $u->schedule_id,
            ])->values()->all(),
        ];
    }
}

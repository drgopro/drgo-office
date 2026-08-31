<?php

namespace App\Services;

use App\Models\LeaveUsage;
use App\Models\Schedule;
use Carbon\Carbon;

/**
 * 연차 사용 원장 동기화 — 캘린더 휴가/개인(red) 일정의 '연차 차감' 체크를
 * leave_usages 원장으로 반영한다. 일정 저장 시마다 해당 일정의 자동 기록을
 * 지우고 다시 계산(멱등)하며, 일정 삭제 시 자동 기록도 함께 사라진다.
 *
 * 규칙:
 * - request_data.leave_deduct = 'full'(연차 1일) | 'half'(반차 0.5일)
 * - 담당자 중 사용자 계정과 연결된(assignee.user_id) 사람에게만 기록
 * - 기간 내 토/일은 차감하지 않는다 (근무일 기준) — 공휴일 보정은 관리 페이지에서 수동
 */
class LeaveLedger
{
    public static function syncSchedule(Schedule $schedule): void
    {
        // 이 일정의 자동 기록 재계산 — 수동 기록(schedule_id null)은 건드리지 않음
        LeaveUsage::where('schedule_id', $schedule->id)->delete();

        if ($schedule->trashed() || $schedule->color !== 'red') {
            return;
        }
        $deduct = data_get($schedule->request_data, 'leave_deduct');
        if (! in_array($deduct, ['full', 'half'], true)) {
            return;
        }

        $userIds = $schedule->assignees()->whereNotNull('user_id')->pluck('user_id')->unique();
        if ($userIds->isEmpty()) {
            return;
        }

        $days = $deduct === 'half' ? 0.5 : 1.0;
        $type = $deduct === 'half' ? '반차' : '연차';
        $start = Carbon::parse($schedule->start_date)->startOfDay();
        $end = Carbon::parse(max((string) $schedule->start_date, (string) $schedule->end_date))->startOfDay();

        $rows = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->isWeekend()) {
                continue; // 토/일 미차감
            }
            foreach ($userIds as $uid) {
                $rows[] = [
                    'user_id' => $uid,
                    'schedule_id' => $schedule->id,
                    'used_on' => $d->format('Y-m-d'),
                    'days' => $days,
                    'type' => $type,
                    'note' => mb_substr((string) $schedule->title, 0, 300),
                    'created_by' => $schedule->created_by,
                ];
            }
        }
        foreach ($rows as $row) {
            LeaveUsage::create($row);
        }
    }

    /**
     * 법정 연차 자동 제안 — 입사일 기준(기본) 또는 회계연도(1/1) 기준.
     * 확정이 아니라 제안값이다 (회사 정책과 다를 수 있음).
     *
     * 회계연도 기준: 입사 이듬해 1/1에 비례연차(15 × 전년 재직일/365, 0.5 단위),
     * 그다음 해부터 15일 + 2년마다 1일 (기산일 1/1).
     *
     * @return array{days: float, label: string}|null
     */
    public static function suggestGrant(?string $hireDate, int $year, bool $fiscal = false): ?array
    {
        if (! $hireDate) {
            return null;
        }
        $hire = Carbon::parse($hireDate);
        if ($hire->year > $year) {
            return null; // 아직 입사 전
        }
        if ($hire->year === $year) {
            // 입사 연도 — 1개월 만근마다 1일 (해당 연도 내 최대치 제안, 기산 방식과 무관)
            $months = min(11, (int) floor($hire->diffInMonths(Carbon::create($year, 12, 31))));

            return ['days' => (float) $months, 'label' => "입사 1년 미만 — 월 1일 발생 (올해 최대 {$months}일)"];
        }
        if ($fiscal && $hire->year === $year - 1) {
            // 회계연도 — 입사 이듬해 1/1 비례연차 (0.5 단위 반올림)
            $worked = (int) floor($hire->diffInDays(Carbon::create($hire->year, 12, 31))) + 1;
            $days = round(15 * $worked / 365 * 2) / 2;

            return ['days' => $days, 'label' => "회계연도 비례연차 — 전년 재직 {$worked}일 기준 {$days}일"];
        }
        // 근속 N년차 — 15일 + 2년마다 1일, 최대 25일 (기산일: 입사기념일 또는 1/1)
        $serviceYears = $year - $hire->year;
        $days = min(25, 15 + intdiv(max(0, $serviceYears - 1), 2));
        $base = $fiscal ? '회계연도 기준 근속' : '근속';

        return ['days' => (float) $days, 'label' => "{$base} {$serviceYears}년차 — 법정 {$days}일"];
    }
}

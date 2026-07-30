<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Notifications\StaleScheduleReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('schedules:remind-stale')]
#[Description('날짜가 지났는데 완료 처리되지 않은 일정 리마인드 — 경과 1·3·7일차에 담당자 웹푸시 (매일 09:10)')]
class RemindStaleSchedules extends Command
{
    /** 리마인드 발송 시점 (종료일 기준 경과 일수) — 날짜 기반이라 별도 상태 컬럼 없이 중복 발송 없음 */
    private const REMIND_AFTER_DAYS = [1, 3, 7];

    public function handle(): int
    {
        $today = now()->startOfDay();
        $dates = collect(self::REMIND_AFTER_DAYS)
            ->map(fn (int $d) => $today->copy()->subDays($d)->format('Y-m-d'))
            ->all();

        // 휴가/개인(red)은 완료 개념이 없어 제외, 하위 일정은 부모에서 관리
        $candidates = Schedule::whereNull('completed_at')
            ->whereNull('parent_id')
            ->where('color', '!=', 'red')
            ->whereIn(DB::raw('coalesce(end_date, start_date)'), $dates)
            ->get();

        $sent = 0;
        foreach ($candidates as $schedule) {
            $end = $schedule->end_date ?? $schedule->start_date;
            $daysOver = (int) $end->copy()->startOfDay()->diffInDays($today);
            foreach ($schedule->notificationRecipients() as $user) {
                $user->notify(new StaleScheduleReminder($schedule, $daysOver));
                $sent++;
            }
        }

        $this->info("대상 일정 {$candidates->count()}건, 알림 {$sent}건 발송");

        return self::SUCCESS;
    }
}

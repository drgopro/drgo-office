<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ScheduleReminder;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('schedules:notify')]
#[Description('알림 시간이 설정된 일정의 시작 전 웹푸시 발송 (매분 실행)')]
class SendScheduleReminders extends Command
{
    /** 발송 시점을 놓친 일정을 소급 발송할 최대 허용 지연 (분) */
    private const LATE_WINDOW_MINUTES = 10;

    public function handle(): int
    {
        $now = now();

        $candidates = Schedule::with('assignees')
            ->whereNull('notified_at')
            ->whereNotNull('notif_minutes')
            ->where('notif_minutes', '!=', '')
            ->whereBetween('start_date', [$now->copy()->subDay()->format('Y-m-d'), $now->copy()->addDay()->format('Y-m-d')])
            ->get();

        $sent = 0;
        foreach ($candidates as $schedule) {
            $notifyAt = $this->notifyAt($schedule);
            if ($notifyAt === null) {
                continue;
            }
            // 발송 시점 도달 전이거나, 너무 오래 지난 건(재기동 폭탄 방지)은 건너뜀
            if ($now->lt($notifyAt) || $notifyAt->diffInMinutes($now) > self::LATE_WINDOW_MINUTES) {
                continue;
            }

            $users = $this->recipients($schedule);
            foreach ($users as $user) {
                $user->notify(new ScheduleReminder($schedule));
            }

            $schedule->update(['notified_at' => $now]);
            $sent++;
        }

        $this->info("검사 {$candidates->count()}건, 발송 {$sent}건");

        return self::SUCCESS;
    }

    /** 알림 발송 시각 — 종일 일정은 당일 09:00, 그 외 시작시간 - notif_minutes */
    private function notifyAt(Schedule $schedule): ?Carbon
    {
        $date = $schedule->start_date?->format('Y-m-d');
        if (! $date) {
            return null;
        }

        if ($schedule->is_all_day || ! $schedule->start_time) {
            return Carbon::parse($date.' 09:00:00');
        }

        $minutes = (int) $schedule->notif_minutes;

        // '하루 전' 옵션(1440분 이상)은 라벨대로 전날 오전 9시에 발송
        if ($minutes >= 1440) {
            return Carbon::parse($date)->subDays(intdiv($minutes, 1440))->setTime(9, 0);
        }

        return Carbon::parse($date.' '.substr($schedule->start_time, 0, 5))->subMinutes($minutes);
    }

    /** 수신자 — 담당자로 연결된 사용자, 없으면 등록자 */
    private function recipients(Schedule $schedule)
    {
        $userIds = $schedule->assignees->pluck('user_id')->filter()->unique();
        if ($userIds->isEmpty() && $schedule->created_by) {
            $userIds = collect([$schedule->created_by]);
        }

        return User::whereIn('id', $userIds)->where('is_active', true)->get();
    }
}

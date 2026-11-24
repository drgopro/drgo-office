<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\Log;

/**
 * 관리자 확인이 필요한 운영 알림 — 관리자·마스터에게 종 알림(+웹푸시)을 보내고,
 * 할 일로 남도록 캘린더(사내업무)에도 같은 건은 한 번만 등록한다.
 */
class OfficeAlert
{
    /**
     * @param  array{date: string, title: string, description?: string, source_type: string, source_id?: int|null}|null  $calendar
     */
    public static function raise(string $title, string $body, string $url, string $tag, ?array $calendar = null): void
    {
        foreach (User::whereIn('role', ['master', 'admin'])->get() as $admin) {
            try {
                $admin->notify(new AdminAlert($title, $body, $url, $tag));
            } catch (\Throwable $e) {
                Log::warning("관리자 알림 발송 실패({$tag}): ".$e->getMessage());
            }
        }

        if ($calendar !== null) {
            self::registerOnCalendar($calendar);
        }
    }

    /**
     * 캘린더 등록 — 같은 출처(source_type·source_id)와 날짜·제목의 일정이 있으면 다시 만들지 않는다.
     *
     * @param  array{date: string, title: string, description?: string, source_type: string, source_id?: int|null}  $calendar
     */
    public static function registerOnCalendar(array $calendar): ?Schedule
    {
        try {
            $exists = Schedule::where('source_type', $calendar['source_type'])
                ->where('source_id', $calendar['source_id'] ?? null)
                ->whereDate('start_date', $calendar['date'])
                ->where('title', $calendar['title'])
                ->exists();
            if ($exists) {
                return null;
            }

            return Schedule::create([
                'title' => $calendar['title'],
                'start_date' => $calendar['date'],
                'end_date' => $calendar['date'],
                'is_all_day' => true,
                'color' => 'blue', // 사내업무
                'description' => $calendar['description'] ?? null,
                'source_type' => $calendar['source_type'],
                'source_id' => $calendar['source_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('관리자 알림 캘린더 등록 실패: '.$e->getMessage());

            return null;
        }
    }
}

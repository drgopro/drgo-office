<?php

namespace App\Console\Commands;

use App\Http\Controllers\CalendarController;
use App\Models\Schedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('calendar:clean-client-links {--fix : 실제로 데이터를 정리 (기본은 조회만)}')]
#[Description('의뢰자 연동 미지원 카테고리(사내업무/휴가·개인)에 과거 누수로 남은 의뢰자 데이터를 정리')]
class CleanCalendarClientLinks extends Command
{
    public function handle(): int
    {
        $contaminated = Schedule::whereIn('color', CalendarController::CLIENT_LINK_EXCLUDED_COLORS)
            ->where(function ($q) {
                $q->whereNotNull('request_data')
                    ->orWhere(fn ($w) => $w->whereNotNull('client_name')->where('client_name', '!=', ''));
            })
            ->orderBy('start_date')
            ->get();

        if ($contaminated->isEmpty()) {
            $this->info('오염된 일정이 없습니다.');
        } else {
            $this->table(
                ['ID', '색상', '날짜', '제목', 'client_name', 'request_data client_id'],
                $contaminated->map(fn ($s) => [
                    $s->id,
                    $s->color,
                    $s->start_date?->format('Y-m-d'),
                    mb_substr($s->title, 0, 30),
                    $s->client_name,
                    $s->request_data['client_id'] ?? '-',
                ])->all()
            );
            $this->warn("연동 미지원 카테고리에 의뢰자 데이터가 남은 일정: {$contaminated->count()}건");

            if ($this->option('fix')) {
                foreach ($contaminated as $s) {
                    $s->update(['client_name' => null, 'request_data' => null]);
                }
                $this->info("{$contaminated->count()}건 정리 완료 (client_name·request_data 제거).");
            } else {
                $this->line('정리하려면 --fix 옵션을 붙여 다시 실행하세요.');
            }
        }

        // 참고 목록: 그 외 카테고리에서 gold 전용 필드가 섞인 비-gold 일정 (과거 누수 의심, 자동 정리하지 않음)
        $legitKeys = ['client_id', 'project_id', 'nickname', 'name', 'phone'];
        $suspicious = Schedule::where('color', '!=', 'gold')
            ->whereNotIn('color', CalendarController::CLIENT_LINK_EXCLUDED_COLORS)
            ->whereNotNull('request_data')
            ->get()
            ->filter(function ($s) use ($legitKeys) {
                $extraKeys = array_diff(array_keys($s->request_data ?? []), $legitKeys);
                $filledIdentity = collect(['nickname', 'name', 'phone'])->contains(fn ($k) => ! empty($s->request_data[$k] ?? null));

                return $extraKeys !== [] || $filledIdentity;
            });

        if ($suspicious->isNotEmpty()) {
            $this->newLine();
            $this->warn("[참고] gold 전용 필드가 섞인 비-gold 일정 {$suspicious->count()}건 — 실제 연동인지 수동 확인 필요:");
            $this->table(
                ['ID', '색상', '날짜', '제목', 'request_data client_id'],
                $suspicious->map(fn ($s) => [
                    $s->id,
                    $s->color,
                    $s->start_date?->format('Y-m-d'),
                    mb_substr($s->title, 0, 30),
                    $s->request_data['client_id'] ?? '-',
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}

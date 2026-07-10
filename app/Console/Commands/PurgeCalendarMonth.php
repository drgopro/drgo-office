<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Models\ScheduleChange;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('calendar:purge-month {month : 삭제할 월 (YYYY-MM)} {--force : 실제로 삭제 (기본은 목록만)}')]
#[Description('지정한 월에 시작하는 캘린더 일정을 일괄 소프트 삭제 (휴지통에서 복구 가능)')]
class PurgeCalendarMonth extends Command
{
    public function handle(): int
    {
        $month = (string) $this->argument('month');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('월 형식이 올바르지 않습니다. 예: 2026-05');

            return self::FAILURE;
        }

        $first = Carbon::parse($month.'-01')->format('Y-m-d');
        $last = Carbon::parse($first)->endOfMonth()->format('Y-m-d');

        $targets = Schedule::whereBetween('start_date', [$first, $last])
            ->orderBy('start_date')
            ->get();

        if ($targets->isEmpty()) {
            $this->info("{$month}에 시작하는 일정이 없습니다.");
        } else {
            $this->table(
                ['ID', '유형', '시작일', '종료일', '제목'],
                $targets->map(fn ($s) => [
                    $s->id, $s->color,
                    $s->start_date?->format('Y-m-d'), $s->end_date?->format('Y-m-d'),
                    mb_substr($s->title, 0, 40),
                ])->all()
            );
            $this->warn("{$month}에 시작하는 일정: {$targets->count()}건");

            if ($this->option('force')) {
                // CLI에는 로그인 사용자가 없으므로 이력 기록용으로 master 계정 사용
                $systemUserId = User::where('role', 'master')->orderBy('id')->value('id');
                foreach ($targets as $s) {
                    ScheduleChange::create([
                        'schedule_id' => $s->id,
                        'user_id' => $systemUserId,
                        'action' => 'delete',
                        'changes' => ['snapshot' => collect($s->getAttributes())->only([
                            'title', 'start_date', 'end_date', 'start_time', 'end_time',
                            'is_all_day', 'color', 'client_name', 'address', 'location',
                            'description', 'is_private',
                        ])->toArray()],
                        'reason' => "월 일괄 삭제 ({$month})",
                    ]);
                    $s->delete(); // 소프트 삭제 — 휴지통에서 복구 가능
                }
                $this->info("{$targets->count()}건 소프트 삭제 완료 (캘린더 휴지통에서 복구 가능).");
            } else {
                $this->line('삭제하려면 --force 옵션을 붙여 다시 실행하세요.');
            }
        }

        // 참고: 이전 달에 시작해 이 달까지 걸치는 다일 일정은 삭제 대상에서 제외됨
        $spanning = Schedule::where('start_date', '<', $first)
            ->where('end_date', '>=', $first)
            ->get(['id', 'color', 'start_date', 'end_date', 'title']);
        if ($spanning->isNotEmpty()) {
            $this->newLine();
            $this->warn("[참고] 이전 달에 시작해 {$month}까지 걸치는 일정 {$spanning->count()}건은 삭제하지 않았습니다:");
            $this->table(
                ['ID', '유형', '시작일', '종료일', '제목'],
                $spanning->map(fn ($s) => [
                    $s->id, $s->color,
                    $s->start_date?->format('Y-m-d'), $s->end_date?->format('Y-m-d'),
                    mb_substr($s->title, 0, 40),
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\BroadcastRoomContract;
use App\Models\RentalContract;
use App\Models\Schedule;
use App\Services\ContractCalendarSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:sync-calendar {--force : 실제로 동기화 (기본은 대상 목록만)}')]
#[Description('렌탈·방송룸 월계약을 캘린더에 동기화 — 미연동 계약 백필 + 진행중 계약 결제 반복 연장')]
class SyncContractCalendars extends Command
{
    public function handle(ContractCalendarSync $sync): int
    {
        $targets = [];

        foreach ([RentalContract::class => '렌탈', BroadcastRoomContract::class => '방송룸'] as $model => $label) {
            foreach ($model::with('client')->where('status', 'active')->get() as $c) {
                $meta = $c->calendar_meta ?? [];
                if (empty($meta)) {
                    $targets[] = ['c' => $c, 'label' => $label, 'reason' => '미연동 (백필)'];

                    continue;
                }
                // 진행중(종료일 없음) 계약: 결제 반복의 남은 기간이 2개월 이내면 재연장
                if (! $c->end_date && ! empty($meta['repeat_group'])) {
                    $lastDate = Schedule::withTrashed()->where('repeat_group', $meta['repeat_group'])->max('start_date');
                    if (! $lastDate || $lastDate <= now()->addMonths(2)->toDateString()) {
                        $targets[] = ['c' => $c, 'label' => $label, 'reason' => '반복 연장'];
                    }
                }
            }
        }

        if (! $targets) {
            $this->info('동기화할 계약이 없습니다.');

            return self::SUCCESS;
        }

        $this->table(
            ['유형', 'ID', '의뢰자', '시작일', '종료일', '사유'],
            collect($targets)->map(fn ($t) => [
                $t['label'], $t['c']->id,
                $t['c']->client?->nickname ?: $t['c']->client?->name,
                $t['c']->start_date?->format('Y-m-d'), $t['c']->end_date?->format('Y-m-d') ?: '(진행중)',
                $t['reason'],
            ])->all()
        );

        if (! $this->option('force')) {
            $this->line('동기화하려면 --force 옵션을 붙여 다시 실행하세요.');

            return self::SUCCESS;
        }

        foreach ($targets as $t) {
            $sync->sync($t['c']);
        }
        $this->info(count($targets).'건 동기화 완료.');

        return self::SUCCESS;
    }
}

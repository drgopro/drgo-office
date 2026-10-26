<?php

namespace App\Console\Commands;

use App\Models\ScheduleShipment;
use App\Services\DeliveryTrackerClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shipments:refresh')]
#[Description('미배송 송장의 배송 상태를 delivery-tracker로 일괄 갱신')]
class RefreshShipments extends Command
{
    public function handle(DeliveryTrackerClient $tracker): int
    {
        if (! config('services.delivery_tracker.url')) {
            $this->warn('DELIVERY_TRACKER_URL 미설정 — 건너뜀');

            return self::SUCCESS;
        }

        // 배송 완료 건은 다시 조회하지 않음. 오래된(30일+) 미완료 건도 제외해 호출량 낭비 방지.
        // 상태값이 고정 목록이 아니어서(manual, 택배사 원문 등) '배송완료 제외' 조건을 유지하고, 100건씩 나눠 읽는다
        $pending = ScheduleShipment::where('status', '!=', 'delivered')
            ->where('created_at', '>=', now()->subDays(30))
            ->lazyById(100);

        $checked = 0;
        $updated = 0;
        foreach ($pending as $shipment) {
            $checked++;
            $before = $shipment->status;
            $tracker->refresh($shipment);
            if ($shipment->status !== $before) {
                $updated++;
            }
        }

        $this->info("조회 {$checked}건, 상태 변경 {$updated}건");

        return self::SUCCESS;
    }
}

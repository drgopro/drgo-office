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

        // 배송 완료 건은 다시 조회하지 않음. 오래된(30일+) 미완료 건도 제외해 낭비 방지.
        $pending = ScheduleShipment::where('status', '!=', 'delivered')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $updated = 0;
        foreach ($pending as $shipment) {
            $before = $shipment->status;
            $tracker->refresh($shipment);
            if ($shipment->status !== $before) {
                $updated++;
            }
        }

        $this->info("조회 {$pending->count()}건, 상태 변경 {$updated}건");

        return self::SUCCESS;
    }
}

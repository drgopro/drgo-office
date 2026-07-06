<?php

namespace App\Services;

use App\Models\ScheduleShipment;
use Illuminate\Support\Facades\Http;

/**
 * 셀프호스팅 delivery-tracker(오픈소스) GraphQL API 클라이언트.
 *
 * POST {base} 로 track(carrierId, trackingNumber) 쿼리를 보내고,
 * 응답을 내부 상태(pending|in_transit|delivered|error)로 정규화한다.
 * 추적 백엔드를 교체할 때는 이 클래스만 바꾸면 된다.
 */
class DeliveryTrackerClient
{
    private const TRACK_QUERY = <<<'GQL'
    query Track($carrierId: ID!, $trackingNumber: String!) {
      track(carrierId: $carrierId, trackingNumber: $trackingNumber) {
        lastEvent {
          time
          description
          status { code name }
          location { name }
        }
      }
    }
    GQL;

    /**
     * 송장 1건 조회 → 정규화된 상태 반환.
     *
     * @return array{status:string, last_event:?string, delivered_at:?string, raw:?array}
     */
    public function fetch(string $carrier, string $trackingNo): array
    {
        $base = rtrim((string) config('services.delivery_tracker.url'), '/');
        if (! $base) {
            return ['status' => 'error', 'last_event' => '추적 서비스 미설정 (DELIVERY_TRACKER_URL)', 'delivered_at' => null, 'raw' => null];
        }

        try {
            $res = Http::timeout(15)->connectTimeout(5)
                ->acceptJson()
                ->post($base, [
                    'query' => self::TRACK_QUERY,
                    'variables' => ['carrierId' => $carrier, 'trackingNumber' => $trackingNo],
                ]);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'last_event' => '추적 서비스 연결 실패', 'delivered_at' => null, 'raw' => null];
        }

        $data = $res->json();

        // GraphQL 에러 (NOT_FOUND = 송장 없음 등)
        if (! $res->ok() || ! empty($data['errors'])) {
            $msg = data_get($data, 'errors.0.message') ?: '조회 실패 (송장번호/택배사 확인)';

            return ['status' => 'error', 'last_event' => mb_substr((string) $msg, 0, 200), 'delivered_at' => null, 'raw' => $data];
        }

        $lastEvent = data_get($data, 'data.track.lastEvent');
        if (! $lastEvent) {
            return ['status' => 'pending', 'last_event' => '배송 정보 없음 (집화 전)', 'delivered_at' => null, 'raw' => $data];
        }

        // TrackEventStatusCode → 내부 상태
        $code = data_get($lastEvent, 'status.code', 'UNKNOWN');
        $status = match (true) {
            $code === 'DELIVERED' => 'delivered',
            in_array($code, ['AT_PICKUP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'ATTEMPT_FAIL', 'AVAILABLE_FOR_PICKUP', 'EXCEPTION'], true) => 'in_transit',
            default => 'pending', // UNKNOWN, INFORMATION_RECEIVED
        };

        $text = trim(implode(' ', array_filter([
            data_get($lastEvent, 'location.name'),
            data_get($lastEvent, 'status.name') ?: data_get($lastEvent, 'description'),
        ])));

        return [
            'status' => $status,
            'last_event' => $text !== '' ? mb_substr($text, 0, 200) : null,
            'delivered_at' => $status === 'delivered' ? (data_get($lastEvent, 'time') ?? now()->toIso8601String()) : null,
            'raw' => $data,
        ];
    }

    /**
     * 송장 모델의 추적 상태를 갱신해 저장.
     */
    public function refresh(ScheduleShipment $shipment): ScheduleShipment
    {
        $result = $this->fetch($shipment->carrier, $shipment->tracking_no);

        $shipment->status = $result['status'];
        $shipment->last_event = $result['last_event'];
        $shipment->checked_at = now();
        if ($result['delivered_at'] && ! $shipment->delivered_at) {
            $shipment->delivered_at = $result['delivered_at'];
        }
        if ($result['raw'] !== null) {
            $shipment->raw = $result['raw'];
        }
        $shipment->save();

        return $shipment;
    }
}

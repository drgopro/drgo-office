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
        // 공식 호스팅 API 키가 있으면 apis.tracker.delivery 사용, 없으면 셀프호스팅 인스턴스
        $clientId = (string) config('services.delivery_tracker.client_id');
        $clientSecret = (string) config('services.delivery_tracker.client_secret');
        $useHosted = $clientId !== '' && $clientSecret !== '';

        $base = $useHosted
            ? 'https://apis.tracker.delivery/graphql'
            : rtrim((string) config('services.delivery_tracker.url'), '/');
        if (! $base) {
            return ['status' => 'error', 'last_event' => '추적 서비스 미설정 (DELIVERY_TRACKER_URL 또는 API 키)', 'delivered_at' => null, 'raw' => null];
        }

        try {
            $req = Http::timeout(15)->connectTimeout(5)->acceptJson();
            if ($useHosted) {
                $req = $req->withHeaders(['Authorization' => "TRACKQL-API-KEY {$clientId}:{$clientSecret}"]);
            }
            $res = $req->post($base, [
                'query' => self::TRACK_QUERY,
                'variables' => ['carrierId' => $carrier, 'trackingNumber' => $trackingNo],
            ]);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'last_event' => '추적 서비스 연결 실패', 'delivered_at' => null, 'raw' => null];
        }

        $data = $res->json();

        // GraphQL 에러 (NOT_FOUND = 송장 없음 등)
        if (! $res->ok() || ! empty($data['errors'])) {
            // 쿠팡: 추적기 인스턴스가 구버전이거나 스크래핑에 실패하면 조회 페이지를 직접 파싱해 폴백
            if ($carrier === 'kr.coupangls' && ($direct = $this->fetchCoupangDirect($trackingNo)) !== null) {
                return $direct;
            }

            $msg = (string) (data_get($data, 'errors.0.message') ?: '조회 실패 (송장번호/택배사 확인)');

            // 추적기 내부 오류(스크래핑 실패·택배사 측 차단 등) — 원문 대신 행동 가능한 안내로 치환
            if (in_array(strtolower(trim($msg)), ['internal error', 'internal server error'], true)) {
                $msg = '택배사 응답을 읽지 못했습니다 — 송장번호 링크로 직접 확인해주세요 (추적 서비스 업데이트 필요 가능성)';
            }

            return ['status' => 'error', 'last_event' => mb_substr($msg, 0, 200), 'delivered_at' => null, 'raw' => $data];
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
     * 쿠팡(CLS) 직접 조회 폴백 — coupangls.com 조회 모달을 파싱 (delivery-tracker 오픈소스와 동일 구조).
     * 파싱 불가(차단·마크업 변경)면 null을 반환해 기존 오류 흐름으로 넘긴다.
     *
     * @return ?array{status:string, last_event:?string, delivered_at:?string, raw:?array}
     */
    private function fetchCoupangDirect(string $trackingNo): ?array
    {
        try {
            $res = Http::timeout(15)->connectTimeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                    'Accept-Language' => 'ko,ko-KR;q=0.9',
                ])
                ->get('https://www.coupangls.com/web/modal/invoice/'.rawurlencode($trackingNo));
        } catch (\Throwable) {
            return null;
        }
        if (! $res->ok()) {
            return null;
        }

        $html = $res->body();
        if (str_contains($html, '운송장 미등록') || stripos($html, 'waybill is not registered') !== false) {
            return ['status' => 'pending', 'last_event' => '운송장 미등록 (집화 전)', 'delivered_at' => null, 'raw' => ['source' => 'coupang_direct']];
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        if (! $doc->loadHTML('<?xml encoding="utf-8"?>'.$html)) {
            return null;
        }
        $xp = new \DOMXPath($doc);
        // .tracking-detail 테이블 행: [시각, 위치, 상태]
        $rows = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tracking-detail ')]//table//tbody//tr");
        $events = [];
        foreach ($rows as $row) {
            $tds = $xp->query('.//td', $row);
            if ($tds->length < 3) {
                continue;
            }
            $events[] = [
                'time' => trim($tds->item(0)->textContent),
                'location' => trim($tds->item(1)->textContent),
                'status' => trim($tds->item(2)->textContent),
            ];
        }
        if (! $events) {
            return null; // 마크업 변경/차단 페이지 — 기존 오류 흐름 유지
        }

        // 최신 이벤트 = 파싱 가능한 시각 기준 최댓값 (행 순서에 의존하지 않음)
        usort($events, fn ($a, $b) => strtotime($a['time']) <=> strtotime($b['time']));
        $last = end($events);
        $delivered = str_contains($last['status'], '배송완료');
        $text = trim(implode(' ', array_filter([$last['location'], $last['status']])));

        return [
            'status' => $delivered ? 'delivered' : 'in_transit',
            'last_event' => $text !== '' ? mb_substr($text, 0, 200) : null,
            'delivered_at' => $delivered ? (date(DATE_ATOM, strtotime($last['time']) ?: time())) : null,
            'raw' => ['source' => 'coupang_direct', 'events' => array_slice($events, -5)],
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

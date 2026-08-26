<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\Schedule;
use App\Models\ScheduleShipment;
use App\Services\DeliveryTrackerClient;
use App\Services\EstimatePaymentSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function __construct(private DeliveryTrackerClient $tracker) {}

    /** 일정의 송장 목록 */
    public function index(Schedule $schedule): JsonResponse
    {
        return response()->json($this->serialize($schedule));
    }

    /** 송장 등록 (등록 즉시 1회 추적 조회) */
    public function store(Request $request, Schedule $schedule): JsonResponse
    {
        return $this->storeFor($request, $schedule->shipments(), fn () => $this->serialize($schedule));
    }

    /** 송장 삭제 */
    public function destroy(ScheduleShipment $shipment): JsonResponse
    {
        $schedule = $shipment->schedule;
        $estimate = $shipment->estimate;
        $shipment->delete();

        return response()->json($schedule ? $this->serialize($schedule) : $this->serializeEstimate($estimate));
    }

    // ── 견적서 주문/배송 송장 — 일정 송장과 동일한 추적 파이프라인 공유 ──

    public function indexForEstimate(Estimate $estimate): JsonResponse
    {
        return response()->json($this->serializeEstimate($estimate));
    }

    public function storeForEstimate(Request $request, Estimate $estimate): JsonResponse
    {
        return $this->storeFor($request, $estimate->shipments(), fn () => $this->serializeEstimate($estimate));
    }

    public function refreshForEstimate(Estimate $estimate): JsonResponse
    {
        $this->refreshPending($estimate->shipments());

        return response()->json($this->serializeEstimate($estimate));
    }

    /** 견적서 송장 삭제 — estimates.edit 권한으로 접근 (일정 송장은 삭제 불가) */
    public function destroyForEstimate(ScheduleShipment $shipment): JsonResponse
    {
        abort_if(! $shipment->estimate_id, 404);
        $estimate = $shipment->estimate;
        $shipment->delete();

        return response()->json($this->serializeEstimate($estimate));
    }

    /** @param  HasMany<ScheduleShipment, covariant \Illuminate\Database\Eloquent\Model>  $shipments */
    private function storeFor(Request $request, $shipments, callable $respond): JsonResponse
    {
        $validated = $request->validate([
            'carrier' => ['required', Rule::in(array_keys(ScheduleShipment::CARRIERS))],
            'tracking_no' => ['required', 'string', 'max:40', 'regex:/^[0-9\-]+$/'],
        ], [
            'tracking_no.regex' => '송장번호는 숫자와 하이픈만 입력할 수 있습니다.',
        ]);

        $trackingNo = str_replace('-', '', $validated['tracking_no']);

        $exists = (clone $shipments)
            ->where('carrier', $validated['carrier'])
            ->where('tracking_no', $trackingNo)
            ->exists();
        if ($exists) {
            return response()->json(['message' => '이미 등록된 송장입니다.'], 422);
        }

        $shipment = $shipments->create([
            'carrier' => $validated['carrier'],
            'tracking_no' => $trackingNo,
            'created_by' => Auth::id(),
        ]);

        $this->tracker->refresh($shipment);

        return response()->json($respond(), 201);
    }

    /**
     * 일정의 미완료 송장 전체 추적 갱신 (상세 열람/수동 새로고침).
     * 추적 API 호출량 제한 보호 — 배송완료 후 2일이 지난 송장은 더 이상
     * 실시간 조회하지 않는다 (송장번호·마지막 상태만 유지). 미완료 송장은
     * 아직 배송 중이므로 계속 추적한다.
     * 배송완료 건의 사업장 위치 백필도 같은 2일 안에서만 (6시간 간격 제한).
     */
    public function refresh(Schedule $schedule): JsonResponse
    {
        $this->refreshPending($schedule->shipments());
        foreach ($this->linkedEstimates($schedule) as $estimate) {
            $this->refreshPending($estimate->shipments());
        }

        return response()->json($this->serialize($schedule));
    }

    /** @param  HasMany<ScheduleShipment, covariant \Illuminate\Database\Eloquent\Model>  $shipments */
    private function refreshPending($shipments): void
    {
        $shipments
            ->where(function ($q) {
                $q->where('status', '!=', 'delivered')
                    ->orWhere(function ($q) {
                        $q->where('status', 'delivered')
                            ->whereNull('last_location')
                            ->where('delivered_at', '>=', now()->subDays(2))
                            ->where(function ($q) {
                                $q->whereNull('checked_at')->orWhere('checked_at', '<', now()->subHours(6));
                            });
                    });
            })
            ->get()
            ->each(fn (ScheduleShipment $s) => $this->tracker->refresh($s));
    }

    /** @return array{shipments: array<int, array<string, mixed>>, carriers: array<string, string>} */
    private function serializeEstimate(Estimate $estimate): array
    {
        return $this->serializeRows($estimate->shipments());
    }

    /**
     * 일정 송장 + 연동 견적서(request_data.estimate_id) 송장 병합 — 송장 입력은 견적서
     * 주문/배송으로 일원화하고 캘린더는 표시만 한다. 과거에 일정에 직접 등록한 송장은
     * 그대로 보이되, 같은 택배사·송장번호가 견적서에도 있으면 견적서 쪽만 남긴다.
     *
     * @return array{shipments: array<int, array<string, mixed>>, carriers: array<string, string>, estimate_id?: int}
     */
    private function serialize(Schedule $schedule): array
    {
        $data = $this->serializeRows($schedule->shipments());
        $estimates = $this->linkedEstimates($schedule);
        if ($estimates->isEmpty()) {
            return $data;
        }

        // 연동된 모든 견적서의 송장 병합 — 같은 택배사·송장번호 중복은 한 번만
        $estRows = collect();
        $seen = [];
        foreach ($estimates as $estimate) {
            foreach ($this->serializeRows($estimate->shipments())['shipments'] as $s) {
                $key = $s['carrier'].'|'.$s['tracking_no'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $estRows->push($s + ['source' => 'estimate', 'estimate_id' => $estimate->id]);
            }
        }
        $ownRows = collect($data['shipments'])
            ->filter(fn ($s) => ! isset($seen[$s['carrier'].'|'.$s['tracking_no']]))
            ->map(fn ($s) => $s + ['source' => 'schedule']);

        $data['shipments'] = $ownRows->concat($estRows)->values()->all();
        $data['estimate_id'] = $estimates->first()->id;
        $data['estimate_ids'] = $estimates->pluck('id')->all();

        return $data;
    }

    /** @return Collection<int, Estimate> */
    private function linkedEstimates(Schedule $schedule)
    {
        $ids = EstimatePaymentSync::scheduleEstimateIds($schedule->request_data ?? []);

        return Estimate::whereIn('id', $ids)->get();
    }

    /**
     * @param  HasMany<ScheduleShipment, covariant \Illuminate\Database\Eloquent\Model>  $relation
     * @return array{shipments: array<int, array<string, mixed>>, carriers: array<string, string>}
     */
    private function serializeRows($relation): array
    {
        $shipments = $relation->orderBy('id')->get()->map(fn (ScheduleShipment $s) => [
            'id' => $s->id,
            'carrier' => $s->carrier,
            'carrier_label' => $s->carrierLabel(),
            'tracking_no' => $s->tracking_no,
            'tracking_url' => $s->trackingUrl(), // 택배사 조회 페이지 (송장번호 자동 입력)
            'status' => $s->status,
            'last_event' => $s->last_event,
            'last_location' => $s->last_location,
            'delivered_at' => $s->delivered_at?->format('Y-m-d H:i'),
            'checked_at' => $s->checked_at?->format('Y-m-d H:i'),
        ]);

        return [
            'shipments' => $shipments->all(),
            'carriers' => ScheduleShipment::CARRIERS,
        ];
    }
}

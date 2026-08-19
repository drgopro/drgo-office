<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\ScheduleShipment;
use App\Services\DeliveryTrackerClient;
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
        $validated = $request->validate([
            'carrier' => ['required', Rule::in(array_keys(ScheduleShipment::CARRIERS))],
            'tracking_no' => ['required', 'string', 'max:40', 'regex:/^[0-9\-]+$/'],
        ], [
            'tracking_no.regex' => '송장번호는 숫자와 하이픈만 입력할 수 있습니다.',
        ]);

        $trackingNo = str_replace('-', '', $validated['tracking_no']);

        $exists = $schedule->shipments()
            ->where('carrier', $validated['carrier'])
            ->where('tracking_no', $trackingNo)
            ->exists();
        if ($exists) {
            return response()->json(['message' => '이미 등록된 송장입니다.'], 422);
        }

        $shipment = $schedule->shipments()->create([
            'carrier' => $validated['carrier'],
            'tracking_no' => $trackingNo,
            'created_by' => Auth::id(),
        ]);

        $this->tracker->refresh($shipment);

        return response()->json($this->serialize($schedule), 201);
    }

    /** 송장 삭제 */
    public function destroy(ScheduleShipment $shipment): JsonResponse
    {
        $schedule = $shipment->schedule;
        $shipment->delete();

        return response()->json($this->serialize($schedule));
    }

    /** 일정의 미완료 송장 전체 추적 갱신 (상세 열람/수동 새로고침) */
    public function refresh(Schedule $schedule): JsonResponse
    {
        $schedule->shipments()
            ->where('status', '!=', 'delivered')
            ->get()
            ->each(fn (ScheduleShipment $s) => $this->tracker->refresh($s));

        return response()->json($this->serialize($schedule));
    }

    /** @return array{shipments: array<int, array<string, mixed>>, carriers: array<string, string>} */
    private function serialize(Schedule $schedule): array
    {
        $shipments = $schedule->shipments()->orderBy('id')->get()->map(fn (ScheduleShipment $s) => [
            'id' => $s->id,
            'carrier' => $s->carrier,
            'carrier_label' => $s->carrierLabel(),
            'tracking_no' => $s->tracking_no,
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

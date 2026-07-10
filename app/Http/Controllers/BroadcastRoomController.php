<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRoomContract;
use App\Models\BroadcastRoomUsage;
use App\Models\Client;
use App\Models\Schedule;
use App\Services\ContractCalendarSync;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BroadcastRoomController extends Controller
{
    public function __construct(private ContractCalendarSync $calendarSync) {}

    public function index()
    {
        $activeContracts = BroadcastRoomContract::where('status', 'active')->count();
        $monthlyRevenue = BroadcastRoomContract::where('status', 'active')->sum('monthly_fee');
        $thisMonthUsage = BroadcastRoomUsage::whereBetween('used_date', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $thisMonthUsageRevenue = BroadcastRoomUsage::whereBetween('used_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('fee');

        return view('broadcast-room.index', compact('activeContracts', 'monthlyRevenue', 'thisMonthUsage', 'thisMonthUsageRevenue'));
    }

    // 월 계약 API
    public function contracts()
    {
        $contracts = BroadcastRoomContract::with('client')->orderByDesc('start_date')->limit(500)->get()->map(fn ($c) => [
            'id' => $c->id,
            'client_id' => $c->client_id,
            'client_name' => $c->client?->name,
            'client_nickname' => $c->client?->nickname,
            'start_date' => $c->start_date?->format('Y-m-d'),
            'end_date' => $c->end_date?->format('Y-m-d'),
            'monthly_fee' => $c->monthly_fee,
            'status' => $c->status,
            'memo' => $c->memo,
            'calendar_synced' => ! empty($c->calendar_meta),
        ]);

        return response()->json($contracts);
    }

    public function storeContract(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'monthly_fee' => 'required|integer|min:0',
            'status' => 'required|in:active,terminated',
            'memo' => 'nullable|string',
        ]);

        $c = DB::transaction(function () use ($validated) {
            $c = BroadcastRoomContract::create($validated);
            $this->calendarSync->sync($c); // 캘린더 양방향 등록 (시작/종료 이벤트 + 결제일 반복)

            return $c;
        });

        return response()->json($c->load('client'), 201);
    }

    public function updateContract(Request $request, BroadcastRoomContract $contract)
    {
        $validated = $request->validate([
            'client_id' => 'sometimes|exists:clients,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'monthly_fee' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,terminated',
            'memo' => 'nullable|string',
        ]);

        DB::transaction(function () use ($contract, $validated) {
            $contract->update($validated);
            $this->calendarSync->sync($contract); // 변경 사항 캘린더 재동기화
        });

        return response()->json($contract->load('client'));
    }

    public function destroyContract(BroadcastRoomContract $contract)
    {
        DB::transaction(function () use ($contract) {
            $this->calendarSync->remove($contract); // 연결된 캘린더 일정 정리
            $contract->delete();
        });

        return response()->json(['ok' => true]);
    }

    // 시간 대여 API
    public function usages(Request $request)
    {
        $query = BroadcastRoomUsage::with('client');

        if ($from = $request->query('from')) {
            $query->where('used_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('used_date', '<=', $to);
        }

        $usages = $query->orderByDesc('start_at')->orderByDesc('used_date')->limit(500)->get()->map(fn ($u) => [
            'id' => $u->id,
            'client_id' => $u->client_id,
            'client_name' => $u->client?->name,
            'client_nickname' => $u->client?->nickname,
            'used_date' => $u->used_date?->format('Y-m-d'),
            'start_at' => $u->start_at?->format('Y-m-d H:i'),
            'end_at' => $u->end_at?->format('Y-m-d H:i'),
            'schedule_id' => $u->schedule_id,
            'hours' => $u->hours,
            'fee' => $u->fee,
            'memo' => $u->memo,
        ]);

        return response()->json($usages);
    }

    public function storeUsage(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'fee' => 'required|integer|min:0',
            'memo' => 'nullable|string',
        ]);

        $usage = DB::transaction(function () use ($validated) {
            $start = Carbon::parse($validated['start_at']);
            $end = Carbon::parse($validated['end_at']);

            // 캘린더 일정 자동 생성 (color=teal = 원격/방송룸)
            $client = Client::find($validated['client_id']);
            $clientLabel = $client ? trim(($client->nickname ?: $client->name).($client->name && $client->nickname ? ' ('.$client->name.')' : '')) : null;
            $schedule = Schedule::create([
                'title' => '🎙 방송룸 대여'.($clientLabel ? ' · '.$clientLabel : ''),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'is_all_day' => false,
                'color' => 'teal',
                'client_name' => $clientLabel,
                'description' => $validated['memo'] ?? null,
                'created_by' => Auth::id(),
            ]);

            return BroadcastRoomUsage::create([
                'client_id' => $validated['client_id'],
                'used_date' => $start->toDateString(),
                'start_at' => $start,
                'end_at' => $end,
                'hours' => round($start->diffInMinutes($end) / 60, 2), // Carbon 3: diffIn*은 부호 있음 — start→end 방향이어야 양수
                'fee' => $validated['fee'],
                'memo' => $validated['memo'] ?? null,
                'schedule_id' => $schedule->id,
            ]);
        });

        return response()->json($usage->load('client'), 201);
    }

    public function updateUsage(Request $request, BroadcastRoomUsage $usage)
    {
        $validated = $request->validate([
            'client_id' => 'sometimes|exists:clients,id',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date|after:start_at',
            'fee' => 'sometimes|integer|min:0',
            'memo' => 'nullable|string',
        ]);

        DB::transaction(function () use ($usage, $validated) {
            if (isset($validated['start_at']) || isset($validated['end_at'])) {
                $start = isset($validated['start_at']) ? Carbon::parse($validated['start_at']) : $usage->start_at;
                $end = isset($validated['end_at']) ? Carbon::parse($validated['end_at']) : $usage->end_at;
                $validated['used_date'] = $start->toDateString();
                $validated['start_at'] = $start;
                $validated['end_at'] = $end;
                $validated['hours'] = round($start->diffInMinutes($end) / 60, 2);
            }
            $usage->update($validated);

            // 연동 스케줄도 동기화
            if ($usage->schedule_id && $usage->start_at && $usage->end_at) {
                $client = $usage->client;
                $clientLabel = $client ? trim(($client->nickname ?: $client->name).($client->name && $client->nickname ? ' ('.$client->name.')' : '')) : null;
                Schedule::where('id', $usage->schedule_id)->update([
                    'title' => '🎙 방송룸 대여'.($clientLabel ? ' · '.$clientLabel : ''),
                    'start_date' => $usage->start_at->toDateString(),
                    'end_date' => $usage->end_at->toDateString(),
                    'start_time' => $usage->start_at->format('H:i:s'),
                    'end_time' => $usage->end_at->format('H:i:s'),
                    'client_name' => $clientLabel,
                    'description' => $usage->memo,
                ]);
            }
        });

        return response()->json($usage->load('client'));
    }

    public function destroyUsage(BroadcastRoomUsage $usage)
    {
        DB::transaction(function () use ($usage) {
            if ($usage->schedule_id) {
                Schedule::where('id', $usage->schedule_id)->delete();
            }
            $usage->delete();
        });

        return response()->json(['ok' => true]);
    }
}

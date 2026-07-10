<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\RentalContract;
use App\Services\ContractCalendarSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RentalContractController extends Controller
{
    public function __construct(private ContractCalendarSync $calendarSync) {}

    public function index(Request $request)
    {
        $query = RentalContract::with('client');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('nickname', 'like', "%{$search}%");
            });
        }

        $contracts = $query->orderByDesc('start_date')->paginate(30);
        $activeCount = RentalContract::where('status', 'active')->count();
        $monthlyRevenue = RentalContract::where('status', 'active')->sum('monthly_fee');

        return view('rental-contracts.index', compact('contracts', 'activeCount', 'monthlyRevenue'));
    }

    public function list()
    {
        $contracts = RentalContract::with('client')->orderByDesc('start_date')->limit(500)->get()->map(fn ($c) => [
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'monthly_fee' => 'required|integer|min:0',
            'status' => 'required|in:active,terminated',
            'memo' => 'nullable|string',
        ]);

        $contract = DB::transaction(function () use ($validated) {
            $contract = RentalContract::create($validated);
            $this->calendarSync->sync($contract); // 캘린더 양방향 등록 (시작/종료 이벤트 + 결제일 반복)

            return $contract;
        });

        return response()->json($contract->load('client'), 201);
    }

    public function update(Request $request, RentalContract $contract)
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

    public function destroy(RentalContract $contract)
    {
        DB::transaction(function () use ($contract) {
            $this->calendarSync->remove($contract); // 연결된 캘린더 일정 정리
            $contract->delete();
        });

        return response()->json(['ok' => true]);
    }

    // 의뢰자 검색 (autocomplete)
    public function searchClients(Request $request)
    {
        $q = $request->query('q', '');
        $clients = Client::where('status', '!=', 'blacklist')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('nickname', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'nickname' => $c->nickname, 'phone' => $c->phone]);

        return response()->json($clients);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRoomContract;
use App\Models\BroadcastRoomUsage;
use Illuminate\Http\Request;

class BroadcastRoomController extends Controller
{
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

        $c = BroadcastRoomContract::create($validated);

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

        $contract->update($validated);

        return response()->json($contract->load('client'));
    }

    public function destroyContract(BroadcastRoomContract $contract)
    {
        $contract->delete();

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

        $usages = $query->orderByDesc('used_date')->limit(500)->get()->map(fn ($u) => [
            'id' => $u->id,
            'client_id' => $u->client_id,
            'client_name' => $u->client?->name,
            'client_nickname' => $u->client?->nickname,
            'used_date' => $u->used_date?->format('Y-m-d'),
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
            'used_date' => 'required|date',
            'hours' => 'required|numeric|min:0',
            'fee' => 'required|integer|min:0',
            'memo' => 'nullable|string',
        ]);

        $u = BroadcastRoomUsage::create($validated);

        return response()->json($u->load('client'), 201);
    }

    public function updateUsage(Request $request, BroadcastRoomUsage $usage)
    {
        $validated = $request->validate([
            'client_id' => 'sometimes|exists:clients,id',
            'used_date' => 'sometimes|date',
            'hours' => 'sometimes|numeric|min:0',
            'fee' => 'sometimes|integer|min:0',
            'memo' => 'nullable|string',
        ]);

        $usage->update($validated);

        return response()->json($usage->load('client'));
    }

    public function destroyUsage(BroadcastRoomUsage $usage)
    {
        $usage->delete();

        return response()->json(['ok' => true]);
    }
}

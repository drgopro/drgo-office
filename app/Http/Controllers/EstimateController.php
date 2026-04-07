<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EstimateController extends Controller
{
    public function index()
    {
        return view('estimates.index');
    }

    public function estimates(Request $request)
    {
        $query = Estimate::with('creator')
            ->where('status', '!=', 'temp')
            ->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('client_nickname', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        return response()->json($query->limit(100)->get());
    }

    public function store()
    {
        $estimate = Estimate::create([
            'status' => 'temp',
            'product_items' => [],
            'service_items' => [],
            'product_total' => 0,
            'service_total' => 0,
            'total_amount' => 0,
            'validity_days' => 3,
            'created_by' => Auth::id(),
        ]);

        return response()->json($estimate, 201);
    }

    public function edit(Estimate $estimate)
    {
        $estimate->load('client', 'creator');
        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]);

        return view('estimates.edit', compact('estimate', 'settings'));
    }

    public function update(Request $request, Estimate $estimate)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'client_name' => 'nullable|string|max:100',
            'client_nickname' => 'nullable|string|max:100',
            'client_phone' => 'nullable|string|max:50',
            'product_items' => 'nullable|array',
            'service_items' => 'nullable|array',
            'status' => 'nullable|in:created,editing,completed,paid,hold',
            'memo' => 'nullable|string',
        ]);

        $productTotal = (int) collect($validated['product_items'] ?? [])->sum('subtotal');
        $serviceTotal = (int) collect($validated['service_items'] ?? [])->sum('amount');

        // temp → created로 자동 전환 (첫 저장 시)
        if ($estimate->status === 'temp' && ! isset($validated['status'])) {
            $validated['status'] = 'created';
        }

        $estimate->update([
            ...$validated,
            'product_total' => $productTotal,
            'service_total' => $serviceTotal,
            'total_amount' => $productTotal + $serviceTotal,
        ]);

        return response()->json($estimate);
    }

    public function issue(Estimate $estimate)
    {
        $estimate->update([
            'status' => 'completed',
            'issued_at' => now(),
        ]);

        return response()->json($estimate);
    }

    public function print(Estimate $estimate)
    {
        $settings = Setting::getMany([
            'seller_name', 'seller_biz_no', 'seller_address',
            'seller_biz_type', 'seller_biz_item', 'seller_phone',
        ]);

        return view('estimates.print', compact('estimate', 'settings'));
    }

    public function destroy(Estimate $estimate)
    {
        $estimate->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }
}

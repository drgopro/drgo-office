<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with('requester', 'approver')
            ->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->limit(100)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier' => 'required|string|max:200',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'expected_date' => 'nullable|date',
            'memo' => 'nullable|string',
        ]);

        $totalAmount = collect($validated['items'])->sum(fn ($i) => $i['qty'] * $i['unit_price']);

        $order = PurchaseOrder::create([
            'status' => 'requested',
            'supplier' => $validated['supplier'],
            'items' => $validated['items'],
            'total_amount' => $totalAmount,
            'requested_by' => Auth::id(),
            'expected_date' => $validated['expected_date'] ?? null,
            'memo' => $validated['memo'] ?? null,
        ]);

        return response()->json($order->load('requester'), 201);
    }

    public function update(Request $request, PurchaseOrder $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:requested,approved,ordered,received,cancelled',
        ]);

        if ($validated['status'] === 'approved') {
            $order->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);
        } else {
            $order->update(['status' => $validated['status']]);
        }

        return response()->json($order->load('requester', 'approver'));
    }

    public function receive(PurchaseOrder $order)
    {
        if ($order->status === 'received') {
            return response()->json(['message' => '이미 입고 처리되었습니다.'], 422);
        }

        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'received',
                'received_date' => now(),
            ]);

            foreach ($order->items as $item) {
                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $item['product_id']],
                    ['quantity' => 0, 'last_updated_at' => now()]
                );

                $newQty = $inventory->quantity + $item['qty'];

                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'movement_type' => 'in',
                    'quantity' => $item['qty'],
                    'quantity_after' => $newQty,
                    'user_id' => Auth::id(),
                    'memo' => "발주입고 (#{$order->id} {$order->supplier})",
                ]);

                $inventory->update([
                    'quantity' => $newQty,
                    'last_updated_at' => now(),
                ]);
            }

            return response()->json($order->load('requester', 'approver'));
        });
    }
}

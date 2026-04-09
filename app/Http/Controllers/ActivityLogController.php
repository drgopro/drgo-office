<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->orderByDesc('created_at');

        // 복수 타입 지원: type=Product,ProductCategory,StockMovement,PurchaseOrder
        if ($request->filled('type')) {
            $types = array_map(
                fn ($t) => 'App\\Models\\'.trim($t),
                explode(',', $request->type)
            );
            if (count($types) === 1) {
                $query->where('loggable_type', $types[0]);
            } else {
                $query->whereIn('loggable_type', $types);
            }
        }

        if ($request->filled('id') && $request->integer('id') > 0) {
            $query->where('loggable_id', $request->id);
        }

        $logs = $query->limit($request->integer('limit', 50))->get();

        return response()->json($logs->map(fn ($log) => [
            'id' => $log->id,
            'action' => $log->action,
            'summary' => $log->summary,
            'changes' => $log->changes,
            'user' => $log->user?->name ?? $log->user?->display_name ?? '(알 수 없음)',
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
        ]));
    }
}

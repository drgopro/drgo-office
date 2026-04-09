<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('loggable_type', 'App\\Models\\'.$request->type);
        }

        if ($request->filled('id')) {
            $query->where('loggable_id', $request->id);
        }

        if ($request->filled('type') && $request->filled('id')) {
            $query->where('loggable_type', 'App\\Models\\'.$request->type)
                ->where('loggable_id', $request->id);
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

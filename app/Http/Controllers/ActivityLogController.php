<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClientDocument;
use App\Models\ClientMemo;
use App\Models\ProjectDocument;
use App\Models\ProjectMemo;
use App\Models\ScheduleAttachment;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    // 모델별 관련 모델 매핑 (부모 → 자식들)
    private const RELATED_MAP = [
        'Client' => [
            ['model' => ClientDocument::class, 'fk' => 'client_id'],
            ['model' => ClientMemo::class, 'fk' => 'client_id'],
        ],
        'Project' => [
            ['model' => ProjectDocument::class, 'fk' => 'project_id'],
            ['model' => ProjectMemo::class, 'fk' => 'project_id'],
        ],
        'Schedule' => [
            ['model' => ScheduleAttachment::class, 'fk' => 'schedule_id'],
        ],
    ];

    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->orderByDesc('created_at');

        // 복수 타입 지원
        if ($request->filled('type')) {
            $types = array_map(
                fn ($t) => 'App\\Models\\'.trim($t),
                explode(',', $request->type)
            );

            $id = $request->filled('id') && $request->integer('id') > 0 ? $request->integer('id') : null;

            // 관련 모델 자동 포함
            $relatedConditions = [];
            foreach (explode(',', $request->type) as $typeName) {
                $typeName = trim($typeName);
                if (isset(self::RELATED_MAP[$typeName]) && $id) {
                    foreach (self::RELATED_MAP[$typeName] as $rel) {
                        $childIds = $rel['model']::where($rel['fk'], $id)->pluck('id')->toArray();
                        if (! empty($childIds)) {
                            $relatedConditions[] = [
                                'type' => $rel['model'],
                                'ids' => $childIds,
                            ];
                        }
                    }
                }
            }

            if ($id && ! empty($relatedConditions)) {
                // 부모 + 자식 모두 조회
                $query->where(function ($q) use ($types, $id, $relatedConditions) {
                    $q->where(function ($sub) use ($types, $id) {
                        if (count($types) === 1) {
                            $sub->where('loggable_type', $types[0]);
                        } else {
                            $sub->whereIn('loggable_type', $types);
                        }
                        $sub->where('loggable_id', $id);
                    });
                    foreach ($relatedConditions as $cond) {
                        $q->orWhere(function ($sub) use ($cond) {
                            $sub->where('loggable_type', $cond['type'])
                                ->whereIn('loggable_id', $cond['ids']);
                        });
                    }
                });
            } else {
                if (count($types) === 1) {
                    $query->where('loggable_type', $types[0]);
                } else {
                    $query->whereIn('loggable_type', $types);
                }
                if ($id) {
                    $query->where('loggable_id', $id);
                }
            }
        } elseif ($request->filled('id') && $request->integer('id') > 0) {
            $query->where('loggable_id', $request->id);
        }

        $logs = $query->limit($request->integer('limit', 100))->get();

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

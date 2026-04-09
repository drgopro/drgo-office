<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->logActivity('create', [], $model->getActivitySummary('create'));
        });

        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getDirty() as $key => $newVal) {
                if (in_array($key, ['updated_at', 'created_at'])) {
                    continue;
                }
                $oldVal = $model->getOriginal($key);
                if (json_encode($oldVal) !== json_encode($newVal)) {
                    $changes[$key] = ['old' => $oldVal, 'new' => $newVal];
                }
            }
            if (! empty($changes)) {
                $model->logActivity('update', $changes, $model->getActivitySummary('update'));
            }
        });

        static::deleted(function ($model) {
            $model->logActivity('delete', [], $model->getActivitySummary('delete'));
        });
    }

    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'loggable')->orderByDesc('created_at');
    }

    public function logActivity(string $action, array $changes = [], ?string $summary = null): void
    {
        ActivityLog::create([
            'loggable_type' => get_class($this),
            'loggable_id' => $this->getKey(),
            'user_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes ?: null,
            'summary' => $summary,
        ]);
    }

    protected function getActivitySummary(string $action): string
    {
        $label = $this->getActivityLabel();
        $actions = ['create' => '생성', 'update' => '수정', 'delete' => '삭제'];

        return $label.' '.($actions[$action] ?? $action);
    }

    protected function getActivityLabel(): string
    {
        $map = [
            'App\\Models\\Client' => '의뢰자',
            'App\\Models\\Project' => '프로젝트',
            'App\\Models\\Estimate' => '견적서',
            'App\\Models\\Schedule' => '일정',
            'App\\Models\\Consultation' => '상담',
            'App\\Models\\Product' => '제품',
            'App\\Models\\PurchaseOrder' => '발주',
        ];

        $type = get_class($this);
        $label = $map[$type] ?? class_basename($type);
        $name = $this->title ?? $this->name ?? $this->client_name ?? "#{$this->getKey()}";

        return "[{$label}] {$name}";
    }
}

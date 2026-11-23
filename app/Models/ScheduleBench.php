<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 캘린더 대기함의 한 건 — 보류(hold)·재방문(revisit)·날짜 미정(undated)·기타(etc).
 * 계산 결과(준비 상태·배치 가능일·단계)는 저장하지 않는다 — App\Services\BenchCalculator가 매번 계산.
 */
class ScheduleBench extends Model
{
    public const KINDS = ['hold', 'revisit', 'undated', 'etc'];

    protected $fillable = [
        'kind', 'status', 'schedule_id', 'origin_date', 'title', 'color', 'address', 'assignee_ids', 'reason',
        'window_type', 'window_from', 'window_to', 'due_date', 'block_condition', 'block_resolved_at',
        'estimate_ids', 'stock_checks', 'prep_answers', 'undo_snapshot',
        'created_by', 'placed_schedule_id', 'placed_at', 'placed_by', 'removed_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'origin_date' => 'date:Y-m-d',
            'window_from' => 'date:Y-m-d',
            'window_to' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'assignee_ids' => 'array',
            'estimate_ids' => 'array',
            'stock_checks' => 'array',
            'prep_answers' => 'array',
            'undo_snapshot' => 'array',
            'block_resolved_at' => 'datetime',
            'placed_at' => 'datetime',
        ];
    }

    /** 원래 일정 자체가 달력에서 빠져 대기하는 종류인지 (보류·기타) — 재방문·날짜 미정은 배치할 때 새 일정을 만든다 */
    public function movesSchedule(): bool
    {
        return in_array($this->kind, ['hold', 'etc'], true);
    }

    /** 배치 전 조건이 남아 있는지 */
    public function isBlocked(): bool
    {
        return filled($this->block_condition) && $this->block_resolved_at === null;
    }

    /**
     * hold·etc: 대기 중인 그 일정 / revisit: 원래 방문 일정. 대기 중 일정은 기본 조회에서 빠지므로 전역 조건 해제.
     *
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class)->withoutGlobalScope('not_benched');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

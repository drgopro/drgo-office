<?php

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    public const PRIORITIES = ['high' => '높음', 'medium' => '중간', 'low' => '낮음'];

    protected $fillable = [
        'title',
        'content',
        'priority',
        'due_date',
        'due_held_at',
        'assignee_id',
        'schedule_id',
        'created_by',
        'completed_at',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'due_held_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> — 대표(첫 번째 선택) 담당자, 칸반 컬럼 배치 기준 */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** 전체 담당자 — 선택 순서대로 (sort_order = 모달에서 클릭한 순서) */
    public function assignees()
    {
        return $this->belongsToMany(User::class, 'todo_assignees')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * 담당자 동기화 — 배열 순서를 보존하고 첫 번째를 대표(assignee_id)로 지정.
     *
     * @param  array<int, int|string>  $ids
     */
    public function syncAssigneesOrdered(array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values();
        $this->assignees()->sync($ids->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());
        if ($ids->isNotEmpty() && $this->assignee_id !== $ids->first()) {
            $this->update(['assignee_id' => $ids->first()]);
        }
    }

    /** @return BelongsTo<Schedule, $this> — 캘린더 연동용 (선택) */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TodoAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TodoAttachment::class);
    }
}

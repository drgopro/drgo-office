<?php

namespace App\Models;

use App\Services\ChannelTalkNotifier;
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
            ->withPivot('sort_order', 'completed_at')
            ->orderByPivot('sort_order');
    }

    /** 체크리스트 (진행 단계) */
    public function checklistItems()
    {
        return $this->hasMany(TodoChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 복수 담당 할 일의 전체 완료 상태 재계산 — 전원이 완료 체크하면 완료, 하나라도 풀리면 미완료.
     * (단독 담당 할 일은 기존 완료 버튼 방식 그대로라 여기서 건드리지 않음)
     */
    public function refreshCompletionFromAssignees(): void
    {
        $pivots = $this->assignees()->get();
        if ($pivots->count() <= 1) {
            return;
        }
        $allDone = $pivots->every(fn ($u) => $u->pivot->completed_at !== null);
        if ($allDone && ! $this->completed_at) {
            $this->update(['completed_at' => now()]);
        } elseif (! $allDone && $this->completed_at) {
            $this->update(['completed_at' => null]);
        }
    }

    /**
     * 담당자 동기화 — 배열 순서를 보존하고 첫 번째를 대표(assignee_id)로 지정.
     *
     * @param  array<int, int|string>  $ids
     */
    public function syncAssigneesOrdered(array $ids, bool $notify = true): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values();
        $changes = $this->assignees()->sync($ids->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());
        if ($ids->isNotEmpty() && $this->assignee_id !== $ids->first()) {
            $this->update(['assignee_id' => $ids->first()]);
        }

        // 담당자 구성이 바뀌면 전원 완료 상태 재계산 (미완료 담당자가 빠져 남은 전원이 완료일 수 있음)
        if ($changes['attached'] || $changes['detached']) {
            $this->refreshCompletionFromAssignees();
        }

        // 담당자 지정/제외 채널톡 알림 (신규 등록은 '새 할 일' 알림과 중복 방지를 위해 notify: false)
        if ($notify && ($changes['attached'] || $changes['detached'])) {
            app(ChannelTalkNotifier::class)
                ->todoAssigneesChanged($this, $changes['attached'], $changes['detached']);
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

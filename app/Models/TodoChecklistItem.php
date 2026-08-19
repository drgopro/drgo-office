<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 할 일 체크리스트 항목 — 진행 단계 (누가/언제 완료했는지 기록) */
class TodoChecklistItem extends Model
{
    protected $fillable = [
        'todo_id',
        'title',
        'done_at',
        'done_by',
        'sort_order',
    ];

    protected $casts = [
        'done_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Todo, $this> */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}

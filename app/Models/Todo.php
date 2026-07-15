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
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
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

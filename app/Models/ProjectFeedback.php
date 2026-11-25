<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectFeedback extends Model
{
    use LogsActivity;

    protected $table = 'project_feedbacks';

    protected $fillable = [
        'project_id',
        'parent_id', // 스레드 — null이면 글, 값이 있으면 그 글의 답글 (1단계)
        'user_id',
        'content',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ProjectFeedback, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** 스레드 답글 — 오래된 순 (대화 흐름대로) @return HasMany<ProjectFeedback, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }
}

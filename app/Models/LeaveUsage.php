<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 연차 사용 원장 — 캘린더 휴가 일정의 '연차 차감' 자동 기록(schedule_id 연결)
 * 또는 경영지원팀 수동 입력(schedule_id null). 반차는 0.5일.
 */
class LeaveUsage extends Model
{
    protected $fillable = [
        'user_id',
        'schedule_id',
        'used_on',
        'days',
        'type',
        'note',
        'created_by',
    ];

    protected $casts = [
        'used_on' => 'date:Y-m-d',
        'days' => 'float',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}

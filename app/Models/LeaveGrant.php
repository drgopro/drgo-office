<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 연도별 부여 연차 — 경영지원팀이 확정한 값 (자동 계산은 제안만) */
class LeaveGrant extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'days',
        'note',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'days' => 'float',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleShipment extends Model
{
    protected $fillable = [
        'schedule_id',
        'carrier',
        'tracking_no',
        'status',
        'last_event',
        'delivered_at',
        'checked_at',
        'raw',
        'created_by',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'checked_at' => 'datetime',
        'raw' => 'array',
    ];

    /** @var array<string, string> delivery-tracker carrier id → 한글 표기 */
    public const CARRIERS = [
        'kr.cjlogistics' => 'CJ대한통운',
        'kr.lotte' => '롯데택배',
        'kr.hanjin' => '한진택배',
        'kr.logen' => '로젠택배',
        'kr.epost' => '우체국택배',
        'kr.kdexp' => '경동택배',
        'kr.coupangls' => '쿠팡',
    ];

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function carrierLabel(): string
    {
        return self::CARRIERS[$this->carrier] ?? $this->carrier;
    }
}

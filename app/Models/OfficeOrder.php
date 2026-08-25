<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 주문 내역의 직접 주문 건 — 사무실 비품/간식 등 견적서와 무관한 주문.
 * 항목은 JSON 스냅샷: [{name, qty, purchase_source, memo}]
 */
class OfficeOrder extends Model
{
    use LogsActivity;

    protected $fillable = [
        'title',
        'items',
        'order_date',
        'created_by',
    ];

    protected $casts = [
        'items' => 'array',
        'order_date' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

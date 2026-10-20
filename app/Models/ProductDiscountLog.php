<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 제품 기간 할인 이력 — 할인 설정/변경(set)·해제(clear) 시점의 값 스냅샷 */
class ProductDiscountLog extends Model
{
    protected $fillable = [
        'product_id',
        'action',
        'sale_price',
        'discount_amount',
        'discount_starts_at',
        'discount_ends_at',
        'user_id',
    ];

    protected $casts = [
        'sale_price' => 'integer',
        'discount_amount' => 'integer',
        'discount_starts_at' => 'datetime',
        'discount_ends_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

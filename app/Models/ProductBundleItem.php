<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 세트 상품 구성품 — 세트 1개당 필요한 구성품과 수량 */
class ProductBundleItem extends Model
{
    protected $fillable = [
        'bundle_product_id',
        'component_product_id',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Product, $this> */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 제품 옵션 그룹 — 같은 상품의 색상 등 구성을 기존 제품 행들로 묶는 껍데기 */
class ProductGroup extends Model
{
    protected $fillable = ['name'];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'group_id');
    }
}

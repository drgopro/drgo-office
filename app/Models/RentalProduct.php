<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 렌탈 상품(렌탈 항목) — 탭(장비/서버)별로 렌탈 담당자가 생성·편집·삭제, 계약 등록 시 금액 자동 입력의 원천 */
class RentalProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['kind', 'name', 'default_fee', 'memo', 'sort_order', 'is_active'];

    protected $casts = [
        'default_fee' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(RentalContract::class, 'product_id');
    }

    /** @param  Builder<RentalProduct>  $query */
    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }
}

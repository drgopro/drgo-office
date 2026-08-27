<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'category',
        'category_id',
        'service_kind',
        'group_id',
        'option_name',
        'purchase_price',
        'sale_price',
        'safety_stock',
        'memo',
        'search_tags',
        'time_required',
        'use_time_required',
        'is_active',
        'show_in_estimate',
        'is_bundle',
    ];

    protected $casts = [
        'purchase_price' => 'integer',
        'sale_price' => 'integer',
        'safety_stock' => 'integer',
        'is_active' => 'boolean',
        'show_in_estimate' => 'boolean',
        'is_bundle' => 'boolean',
        'use_time_required' => 'boolean',
    ];

    public function marketPrices()
    {
        return $this->hasMany(ProductMarketPrice::class);
    }

    public function categoryRelation()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * 서비스/제품 분류 — 매출 통계에서 세팅비(서비스) 매출과 장비판매 매출을 가르는 기준.
     * 제품별 재정의(service_kind)가 있으면 그것을, 없으면 소속 카테고리(조상 포함)의
     * '서비스' 체크를 따른다. 견적서에 담는 순간 스냅샷(item.is_service)으로 박제된다.
     */
    public function isService(): bool
    {
        if ($this->service_kind === 'service') {
            return true;
        }
        if ($this->service_kind === 'product') {
            return false;
        }
        $cat = $this->categoryRelation;
        for ($i = 0; $cat && $i < 6; $i++) {
            if ($cat->is_service) {
                return true;
            }
            $cat = $cat->parent;
        }

        return false;
    }

    /** 옵션 그룹 (블랙/화이트 등 같은 상품 묶음) */
    public function group()
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /** 세트 구성품 목록 (이 제품이 세트일 때) */
    public function bundleItems()
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id')->orderBy('sort_order');
    }

    /** 이 제품을 구성품으로 포함하는 세트들 (삭제 가드용) */
    public function bundledIn()
    {
        return $this->hasMany(ProductBundleItem::class, 'component_product_id');
    }

    /**
     * 세트 조립 가능 수 — min(구성품 재고 ÷ 필요 수량). 세트가 아니거나 구성품이 없으면 null.
     * bundleItems.component.inventory가 eager load 되어 있어야 N+1이 없다.
     */
    public function buildableQuantity(): ?int
    {
        if (! $this->is_bundle || $this->bundleItems->isEmpty()) {
            return $this->is_bundle ? 0 : null;
        }

        return (int) $this->bundleItems->map(function ($item) {
            $stock = max(0, (int) ($item->component?->inventory?->quantity ?? 0));

            return intdiv($stock, max(1, $item->quantity));
        })->min();
    }
}

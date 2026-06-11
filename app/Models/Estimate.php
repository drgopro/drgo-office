<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * 견적서 — 스냅샷 정책 (중요)
 *
 * product_items / service_items는 견적서 작성 시점의 제품/서비스 정보를
 * 그대로 JSON으로 보존합니다.
 * - 항목 형식: { product_id, sku, category, name, purchase_price, sale_price, qty, subtotal, time_required }
 * - product_id는 참조만 — 출력/계산은 JSON의 name/sale_price/subtotal에 의존
 * - 제품 가격·이름이 바뀌어도 기존 견적서는 변하지 않음
 * - 제품이 삭제(soft delete)되어도 기존 견적서의 항목 정보는 그대로 유지
 *
 * 절대 하지 말 것:
 *  - product_id로 Product 테이블을 다시 조회해 name/price를 덮어쓰는 코드
 *  - 견적서 표시/인쇄 시 현재 Product를 join 하는 쿼리
 */
class Estimate extends Model
{
    use LogsActivity;

    protected $fillable = [
        'client_id',
        'project_id',
        'client_name',
        'client_nickname',
        'client_phone',
        'product_items',
        'service_items',
        'product_total',
        'service_total',
        'total_amount',
        'category_breakdown',
        'status',
        'validity_days',
        'issued_at',
        'memo',
        'created_by',
    ];

    protected $casts = [
        'product_items' => 'array',
        'service_items' => 'array',
        'category_breakdown' => 'array',
        'product_total' => 'integer',
        'service_total' => 'integer',
        'total_amount' => 'integer',
        'validity_days' => 'integer',
        'issued_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

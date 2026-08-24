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
        'share_token',
        'payapp_mul_no',
        'payapp_payurl',
        'payapp_state',
        'payapp_requested_at',
        'payapp_paid_at',
        'memo',
        'internal_memo',
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
        'payapp_state' => 'integer',
        'payapp_requested_at' => 'datetime',
        'payapp_paid_at' => 'datetime',
    ];

    /**
     * 의뢰자에게 전달하는 공개 견적서 링크.
     * 순번 ID 대신 난수 토큰(64자)을 사용해 주소 조작으로 다른 견적서를
     * 열람할 수 없다. 토큰은 최초 호출 시 생성 후 고정.
     * ESTIMATE_PUBLIC_BASE_URL 설정 시 office 서브도메인 대신 그 도메인으로 발급.
     */
    public function publicUrl(): string
    {
        if (! $this->share_token) {
            $this->share_token = bin2hex(random_bytes(32));
            $this->saveQuietly();
        }

        $base = (string) config('services.estimate_share.base_url');
        if ($base !== '') {
            return rtrim($base, '/').'/estimate-view/'.$this->share_token;
        }

        return route('estimates.public', ['token' => $this->share_token]);
    }

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

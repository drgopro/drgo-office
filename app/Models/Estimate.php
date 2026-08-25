<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * 견적서 — 스냅샷 정책 (중요)
 *
 * product_items / service_items는 견적서 작성 시점의 제품/서비스 정보를
 * JSON으로 보존합니다.
 * - 항목 형식: { product_id, sku, category, name, purchase_price, sale_price, qty, subtotal, time_required }
 * - product_id는 참조만 — 출력/계산은 JSON의 name/sale_price/subtotal에 의존
 * - 제품이 삭제(soft delete)되어도 기존 견적서의 항목 정보는 그대로 유지
 *
 * 단가 동기화 정책 (syncSnapshotPrices):
 * - 발행 완료(issued)/결제 완료(paid)/결제 취소(cancelled) 이후에는 그 시점 가격을 영구 보존 (아카이브)
 *   — 발행 후에도 갱신하면 페이앱 결제요청 금액과 어긋나므로 issued부터 고정
 * - 그 이전 상태에서는 열람(빌더/출력/공개 링크) 시 현재 제품 판매가·매입가로 갱신
 * - 이름/카테고리 등 가격 외 스냅샷 필드는 어떤 상태에서도 덮어쓰지 않는다
 */
class Estimate extends Model
{
    use LogsActivity;

    /** 화면 표시용 번호 — 발급 전(temp)이나 구버전 행은 id로 폴백 */
    protected $appends = ['display_no'];

    public function getDisplayNoAttribute(): int
    {
        return $this->estimate_no ?? $this->id;
    }

    protected $fillable = [
        'estimate_no',
        'title',
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
        'draft',
        'draft_saved_at',
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
        'draft' => 'array',
        'draft_saved_at' => 'datetime',
    ];

    /** 이 상태들부터는 품목 단가를 고정 보존 — 발행 후 갱신하면 결제요청 금액과 어긋난다 */
    public const PRICE_LOCKED_STATUSES = ['issued', 'paid', 'cancelled'];

    /**
     * 품목 단가를 현재 제품 판매가·매입가로 동기화 (가격 잠금 상태 제외).
     * 가격 외 스냅샷 필드(이름·카테고리 등)와 수동 항목·삭제된 제품 항목은 건드리지 않는다.
     * 변경이 있을 때만 저장하며, 저장 여부를 반환.
     */
    public function syncSnapshotPrices(): bool
    {
        if (in_array($this->status, self::PRICE_LOCKED_STATUSES, true)) {
            return false;
        }

        $items = $this->product_items ?? [];
        $ids = collect($items)->pluck('product_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return false;
        }

        $products = Product::whereIn('id', $ids)->get(['id', 'sale_price', 'purchase_price'])->keyBy('id');
        $changed = false;
        foreach ($items as &$item) {
            $product = ! empty($item['product_id']) ? $products->get($item['product_id']) : null;
            if (! $product) {
                continue;
            }
            $newSale = (int) $product->sale_price;
            $newPurchase = (int) $product->purchase_price;
            $qty = max(1, (int) ($item['qty'] ?? 1));
            if ((int) ($item['sale_price'] ?? 0) !== $newSale || (int) ($item['purchase_price'] ?? 0) !== $newPurchase) {
                $item['sale_price'] = $newSale;
                $item['purchase_price'] = $newPurchase;
                $item['subtotal'] = $newSale * $qty;
                $changed = true;
            }
        }
        unset($item);

        if (! $changed) {
            return false;
        }

        $productTotal = (int) collect($items)->sum('subtotal');
        $this->forceFill([
            'product_items' => $items,
            'product_total' => $productTotal,
            'total_amount' => $productTotal + (int) $this->service_total,
        ])->save();

        return true;
    }

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

    /** 주문/배송 운송장 — 캘린더 일정 송장과 동일한 추적 파이프라인 공유 */
    public function shipments()
    {
        return $this->hasMany(ScheduleShipment::class);
    }
}

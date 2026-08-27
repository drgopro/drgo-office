<?php

namespace App\Models;

use App\Services\EstimateStockSync;
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
     * 잠금 상태(발행/결제/취소)에서도 세트 구성품의 '누락된' 참고 가격만은 백필한다
     * — 부분환불 계산에 필요하지만 가격 필드 도입 전 스냅샷에는 없기 때문 (기존 값은 불변).
     * 변경이 있을 때만 저장하며, 저장 여부를 반환.
     */
    public function syncSnapshotPrices(): bool
    {
        $locked = in_array($this->status, self::PRICE_LOCKED_STATUSES, true);

        $items = $this->product_items ?? [];
        $ids = collect($items)->pluck('product_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return false;
        }

        $products = Product::with('bundleItems.component')->whereIn('id', $ids)->get()->keyBy('id');
        $changed = false;
        foreach ($items as &$item) {
            $product = ! empty($item['product_id']) ? $products->get($item['product_id']) : null;
            if (! $product) {
                continue;
            }
            $newSale = (int) $product->sale_price;
            $newPurchase = (int) $product->purchase_price;
            $qty = max(1, (int) ($item['qty'] ?? 1));
            // price_fixed: 엑셀 가져오기 등으로 담은 과거 가격 항목 — 현재 판매가로 덮어쓰지 않는다
            if (! $locked && empty($item['price_fixed']) && ((int) ($item['sale_price'] ?? 0) !== $newSale || (int) ($item['purchase_price'] ?? 0) !== $newPurchase)) {
                $item['sale_price'] = $newSale;
                $item['purchase_price'] = $newPurchase;
                $item['subtotal'] = $newSale * $qty;
                $changed = true;
            }
            // 담은 뒤에 세트로 바뀐 제품 — 스냅샷에 구성이 없으면 현재 세트 구성을 백필해
            // 빌더 펼치기·부분환불에서 세트로 보이게 한다 (잠금 상태 포함, 발행일시 불변)
            if ($product->is_bundle && empty($item['bundle_items']) && $product->bundleItems->isNotEmpty()) {
                $item['bundle_items'] = $product->bundleItems->map(fn ($bi) => [
                    'name' => $bi->component?->name ?? '(삭제된 구성품)',
                    'qty' => max(1, (int) $bi->quantity),
                    'price' => (int) ($bi->component?->sale_price ?? 0),
                ])->values()->all();
                $changed = true;
            }
            // 세트 구성품 참고 가격 — 구버전 스냅샷 백필 + 변동 반영 (부분환불 계산용).
            // 잠금 상태에서는 이미 기록된 값은 보존하고 '누락'만 채운다.
            if ($product->is_bundle && ! empty($item['bundle_items']) && is_array($item['bundle_items'])) {
                $components = $product->bundleItems->keyBy(fn ($bi) => $bi->component?->name ?? '');
                foreach ($item['bundle_items'] as $bIdx => $b) {
                    $newPrice = (int) ($components->get($b['name'] ?? '')?->component?->sale_price ?? 0);
                    $current = (int) ($b['price'] ?? 0);
                    if ($newPrice > 0 && ($locked ? $current === 0 : $current !== $newPrice)) {
                        $item['bundle_items'][$bIdx]['price'] = $newPrice;
                        $changed = true;
                    }
                }
            }
        }
        unset($item);

        if (! $changed) {
            return false;
        }

        if ($locked) {
            // 잠금 상태의 백필은 구성품 참고 가격만 — 합계·발행일시(updated_at)는 건드리지 않는다
            $this->timestamps = false;
            $this->forceFill(['product_items' => $items])->save();
            $this->timestamps = true;

            return true;
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
     * 항목별 환불/결제취소 기록 — 프로젝트 환불 처리·주문 내역 수동 체크가 호출.
     * refunds: [{index:int, qty:int, amount:int}] — 해당 스냅샷 항목에 누적 기록.
     * 금액 원장은 ProjectPayment가 담당하므로 여기서는 표시용 기록만 남기며,
     * 발행일시(updated_at)와 품목 단가·합계는 건드리지 않는다.
     *
     * @param  array<int, array{index:int, qty?:int, amount?:int}>  $refunds
     */
    public function applyItemRefunds(array $refunds): bool
    {
        $items = $this->product_items ?? [];
        $before = $items; // 직접발송 재고 복원 판단용 — 변경 전 스냅샷
        $changed = false;
        foreach ($refunds as $r) {
            $idx = (int) ($r['index'] ?? -1);
            if (! array_key_exists($idx, $items)) {
                continue;
            }
            $qty = max(0, (int) ($r['qty'] ?? 0));
            $amount = max(0, (int) ($r['amount'] ?? 0));
            // 세트 구성품 단위 부분환불 — bundle_index가 있으면 구성품에 수량 기록, 항목에는 금액만 합산
            if (isset($r['bundle_index']) && isset($items[$idx]['bundle_items'][(int) $r['bundle_index']])) {
                $b = (int) $r['bundle_index'];
                $items[$idx]['bundle_items'][$b]['refund_qty'] = (int) ($items[$idx]['bundle_items'][$b]['refund_qty'] ?? 0) + $qty;
                $items[$idx]['bundle_items'][$b]['refund_amount'] = (int) ($items[$idx]['bundle_items'][$b]['refund_amount'] ?? 0) + $amount;
            } else {
                $items[$idx]['refund_qty'] = (int) ($items[$idx]['refund_qty'] ?? 0) + $qty;
            }
            $items[$idx]['refunded'] = true;
            $items[$idx]['refund_amount'] = (int) ($items[$idx]['refund_amount'] ?? 0) + $amount;
            $items[$idx]['refunded_at'] = now()->format('Y-m-d H:i');
            $changed = true;
        }
        if (! $changed) {
            return false;
        }
        $this->timestamps = false;
        $this->forceFill(['product_items' => $items])->save();
        $this->timestamps = true;
        // 직접발송으로 나갔던 수량이 환불되면 그만큼 재고 복원
        EstimateStockSync::apply($this, $before, $items);

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

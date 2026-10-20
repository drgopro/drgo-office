<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductDiscountLog;
use Illuminate\Console\Command;

/**
 * 기간이 지난 제품 할인 자동 해제 — 할인 필드를 초기화하고(체크 해제 상태)
 * 이력에 '기간 종료(expire)' 행을 남겨 언제 어떤 할인이 끝났는지 추적한다.
 */
class ExpireProductDiscounts extends Command
{
    protected $signature = 'products:expire-discounts';

    protected $description = '종료 일시가 지난 제품 기간 할인을 해제하고 이력에 기간 종료로 기록';

    public function handle(): int
    {
        $expired = Product::where('discount_amount', '>', 0)
            ->whereNotNull('discount_ends_at')
            ->where('discount_ends_at', '<', now())
            ->get();

        foreach ($expired as $product) {
            ProductDiscountLog::create([
                'product_id' => $product->id,
                'action' => 'expire',
                'sale_price' => (int) $product->sale_price,
                'discount_amount' => (int) $product->discount_amount,
                'discount_starts_at' => $product->discount_starts_at,
                'discount_ends_at' => $product->discount_ends_at,
                'user_id' => null, // 시스템 자동 처리
            ]);
            $product->update([
                'discount_amount' => 0,
                'discount_starts_at' => null,
                'discount_ends_at' => null,
            ]);
        }

        $this->info("기간 종료 할인 {$expired->count()}건 해제");

        return self::SUCCESS;
    }
}

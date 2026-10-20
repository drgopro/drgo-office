<?php

namespace App\Console\Commands;

use App\Models\Product;
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
        $count = Product::clearExpiredDiscounts();

        $this->info("기간 종료 할인 {$count}건 해제");

        return self::SUCCESS;
    }
}

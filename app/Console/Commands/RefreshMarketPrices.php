<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CompuzoneClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:refresh-market-prices {--limit=200} {--sleep=1500}')]
#[Description('컴퓨존 시세 URL이 등록된 제품의 시세를 일괄 갱신')]
class RefreshMarketPrices extends Command
{
    public function handle(CompuzoneClient $compuzone): int
    {
        // 미조회(null) → 오래된 순으로 우선 갱신 (MySQL/sqlite 모두 asc에서 NULL 우선)
        $products = Product::whereNotNull('market_price_url')
            ->where('is_active', true)
            ->orderBy('market_price_checked_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($products->isEmpty()) {
            $this->warn('시세 URL이 등록된 제품이 없습니다 — 건너뜀');

            return self::SUCCESS;
        }

        $sleepMs = max(0, (int) $this->option('sleep'));
        $ok = 0;
        $fail = 0;
        foreach ($products as $i => $product) {
            // 연속 요청 간 대기 — 차단 위험 완화
            if ($i > 0 && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
            $compuzone->refresh($product) ? $ok++ : $fail++;
        }

        $summary = "시세 갱신 — 대상 {$products->count()}건, 성공 {$ok}건, 실패 {$fail}건";
        $this->info($summary);
        @file_put_contents(storage_path('logs/compuzone.log'), '['.now()->format('Y-m-d H:i:s')."] {$summary}\n", FILE_APPEND);

        return self::SUCCESS;
    }
}

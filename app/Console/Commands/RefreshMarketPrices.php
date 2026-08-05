<?php

namespace App\Console\Commands;

use App\Models\ProductMarketPrice;
use App\Services\MarketPriceCrawler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:refresh-market-prices {--limit=400} {--sleep=1500}')]
#[Description('판매처(컴퓨존·피씨팩토리) 시세 URL이 등록된 제품의 시세를 일괄 갱신')]
class RefreshMarketPrices extends Command
{
    public function handle(MarketPriceCrawler $crawler): int
    {
        // 판매처별 행 단위로 갱신 — 미조회(null) → 오래된 순 우선 (asc에서 NULL 우선)
        $rows = ProductMarketPrice::whereHas('product', fn ($q) => $q->where('is_active', true))
            ->orderBy('checked_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('시세 URL이 등록된 제품이 없습니다 — 건너뜀');

            return self::SUCCESS;
        }

        $sleepMs = max(0, (int) $this->option('sleep'));
        $ok = 0;
        $fail = 0;
        foreach ($rows as $i => $row) {
            // 연속 요청 간 대기 — 차단 위험 완화
            if ($i > 0 && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
            $crawler->refresh($row) ? $ok++ : $fail++;
        }

        $summary = "시세 갱신 — 대상 {$rows->count()}건(판매처 단위), 성공 {$ok}건, 실패 {$fail}건";
        $this->info($summary);
        @file_put_contents(storage_path('logs/compuzone.log'), '['.now()->format('Y-m-d H:i:s')."] {$summary}\n", FILE_APPEND);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\RevenueLedger;
use Illuminate\Console\Command;

/** 매출 인식 원장 전체 재구축 — 규칙 변경·분류 변경·롤백 복구 후 언제든 실행 가능 (멱등) */
class RebuildRevenueLedger extends Command
{
    protected $signature = 'revenue:rebuild';

    protected $description = '매출 인식 원장(revenue_entries)을 견적서·결제 원장에서 처음부터 다시 계산합니다';

    public function handle(): int
    {
        $count = RevenueLedger::rebuild();
        $this->info("매출 원장 재구축 완료 — {$count}건 처리");

        return self::SUCCESS;
    }
}

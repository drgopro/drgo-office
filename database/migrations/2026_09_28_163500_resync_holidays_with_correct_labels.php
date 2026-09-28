<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * 공휴일 재동기화 — 소스를 공공데이터 기반(holidays-kr)으로 교체하면서
     * 이전 소스가 저장한 잘못된 대체공휴일 명칭(예: 10/5 '개천절')을 교정.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return; // 테스트 DB 마이그레이션에서 외부 API 호출 금지
        }

        try {
            Artisan::call('holidays:sync');
        } catch (Throwable) {
            // 다음 주간 스케줄에서 재시도
        }
    }

    public function down(): void
    {
        // 데이터 시드성 작업 — 롤백 없음
    }
};

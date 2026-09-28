<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * 공휴일 최초 1회 동기화 — 주간 스케줄(월 03:20)을 기다리지 않고 배포 직후 반영.
     * 외부 API 실패가 배포(마이그레이션)를 막지 않도록 항상 통과시킨다.
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

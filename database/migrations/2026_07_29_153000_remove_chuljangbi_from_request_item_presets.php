<?php

use App\Models\RequestItemPreset;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** 출장비는 세팅 작업이 아니라 요금 항목이라 의뢰 세부 항목 선택지에서 제외 */
    public function up(): void
    {
        RequestItemPreset::where('title', '출장비')->delete();
    }

    public function down(): void
    {
        // 데이터 정리 마이그레이션 — 필요 시 가격표 동기화 마이그레이션을 다시 실행해 복원
    }
};

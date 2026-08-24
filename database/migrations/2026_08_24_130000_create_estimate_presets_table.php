<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 견적 프리셋 — 자주 쓰는 품목 구성을 저장해 견적서에 불러오기 (여러 개 조립 가능) */
    public function up(): void
    {
        if (! Schema::hasTable('estimate_presets')) {
            Schema::create('estimate_presets', function (Blueprint $table) {
                $table->id();
                $table->string('title', 200);
                $table->json('items'); // 장바구니와 동일 스냅샷 구조 (수기 항목 포함)
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_presets');
    }
};

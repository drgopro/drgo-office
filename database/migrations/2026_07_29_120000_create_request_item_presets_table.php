<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_item_presets', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->comment('1뎁스 세팅 타이틀 (예: 처음 세팅)');
            $table->json('children')->nullable()->comment('2뎁스 분류 → 3뎁스 항목 배열 {"오디오":["마이크 세팅",...]}');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 초기 프리셋 — 캘린더 의뢰 세부 항목 선택지
        $now = now();
        DB::table('request_item_presets')->insert([
            [
                'title' => '처음 세팅',
                'children' => json_encode([
                    '컴퓨터' => ['컴퓨터 문제해결'],
                    '오디오' => ['오디오 인터페이스 세팅', '마이크 세팅'],
                    '비디오' => [],
                    '조명' => [],
                ], JSON_UNESCAPED_UNICODE),
                'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'title' => '세팅 개선',
                'children' => json_encode([
                    '컴퓨터' => [],
                    '오디오' => [],
                    '비디오' => [],
                    '조명' => [],
                ], JSON_UNESCAPED_UNICODE),
                'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'title' => '이사 세팅',
                'children' => json_encode([
                    '공통' => ['장비 운반', '장비 해체', '장비 배치'],
                ], JSON_UNESCAPED_UNICODE),
                'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('request_item_presets');
    }
};

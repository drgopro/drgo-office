<?php

use App\Models\ConsultationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 유형별 진행 단계 개편 2/2 — 시드 (DML)
     * - 기존 유형 라벨 갱신: 방문세팅→방문, 원격세팅→원격, 디자인→제작, 단순문의→문의, 상품 문의→상품
     * - 신규 유형 추가: 내방(store), 촬영(filming), 렌탈(rental), 방송룸(broadcast)
     * - 전 유형에 stages flow JSON 저장 (기획 '유형별 진행 단계' 기준, A/S·문제해결은 기존 흐름 유지)
     */
    public function up(): void
    {
        $now = now();
        $sortOrder = (int) DB::table('consultation_types')->max('sort_order');

        foreach (ConsultationType::DEFAULT_FLOWS as $key => $flow) {
            $label = ConsultationType::DEFAULTS[$key] ?? $key;
            $existing = DB::table('consultation_types')->where('key', $key)->first();

            if ($existing) {
                DB::table('consultation_types')->where('key', $key)->update([
                    'label' => $label,
                    'stages' => json_encode($flow, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('consultation_types')->insert([
                    'key' => $key,
                    'label' => $label,
                    'stages' => json_encode($flow, JSON_UNESCAPED_UNICODE),
                    'sort_order' => ++$sortOrder,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('consultation_types')->update(['stages' => null]);
        DB::table('consultation_types')->whereIn('key', ['store', 'filming', 'rental', 'broadcast'])->delete();
    }
};

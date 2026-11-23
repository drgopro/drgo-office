<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 렌탈 유형 진행 단계에 '렌탈 이용'·'완료' 추가 (피드백 #290) — 회수 후 완료 처리할 단계가 없던 문제.
     * 이미 저장된 flow 뒤에 없는 단계만 덧붙인다 (기존 단계·라벨은 건드리지 않음).
     */
    public function up(): void
    {
        $row = DB::table('consultation_types')->where('key', 'rental')->first();
        $stages = $row ? json_decode((string) $row->stages, true) : null;
        if (empty($stages) || ! is_array($stages)) {
            return; // 저장된 flow 없음 — 코드의 기본 flow(DEFAULT_FLOWS)를 그대로 쓴다
        }

        $codes = array_column($stages, 'code');
        foreach ([
            ['code' => 'visit', 'label' => '렌탈 이용', 'kind' => 'work'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ] as $stage) {
            if (! in_array($stage['code'], $codes, true)) {
                $stages[] = $stage;
            }
        }

        DB::table('consultation_types')->where('key', 'rental')->update([
            'stages' => json_encode($stages, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 되돌리지 않는다 — 이미 '렌탈 이용'·'완료' 단계에 있는 프로젝트가 flow 밖으로 밀려난다
    }
};

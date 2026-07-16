<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 작업 유형을 프로젝트 유형 종속 구조로 개편.
     * - work_types.type_key 추가 (consultation_types.key 참조, null=공통)
     * - 기획안 9종 유형별 작업 유형 시드 (기존 키는 재사용해 프로젝트 데이터 보존,
     *   기획안에 없는 레거시 작업 유형은 비활성화)
     */
    public function up(): void
    {
        if (! Schema::hasColumn('work_types', 'type_key')) {
            Schema::table('work_types', function (Blueprint $table) {
                $table->string('type_key', 50)->nullable()->after('key')->comment('종속 프로젝트 유형 (consultation_types.key, null=공통)');
            });
        }

        // 프로젝트 유형 → 작업 유형 (기획안). 기존 키(troubleshoot/as/survey/design/hourly/monthly)는 재사용.
        $seed = [
            ['key' => 'simple', 'type_key' => 'inquiry', 'label' => '단순'],
            ['key' => 'troubleshoot', 'type_key' => 'remote', 'label' => '문제해결'],
            ['key' => 'as', 'type_key' => 'remote', 'label' => 'AS'],
            ['key' => 'general', 'type_key' => 'visit', 'label' => '일반'],
            ['key' => 'revisit', 'type_key' => 'visit', 'label' => '재방문'],
            ['key' => 'rush', 'type_key' => 'visit', 'label' => '급행'],
            ['key' => 'survey', 'type_key' => 'visit', 'label' => '사전답사'],
            ['key' => 'outdoor_setup', 'type_key' => 'store', 'label' => '야외방송 세팅(1차)'],
            ['key' => 'edu_consulting', 'type_key' => 'store', 'label' => '교육·컨설팅'],
            ['key' => 'relay', 'type_key' => 'filming', 'label' => '중계'],
            ['key' => 'outdoor', 'type_key' => 'filming', 'label' => '야외'],
            ['key' => 'design', 'type_key' => 'design', 'label' => '디자인'],
            ['key' => 'video', 'type_key' => 'design', 'label' => '영상'],
            ['key' => 'audio', 'type_key' => 'design', 'label' => '음향'],
            ['key' => 'rental', 'type_key' => 'rental', 'label' => '렌탈'],
            ['key' => 'equip_single', 'type_key' => 'rental', 'label' => '장비(단품)'],
            ['key' => 'hourly', 'type_key' => 'broadcast', 'label' => '시간대여'],
            ['key' => 'daily', 'type_key' => 'broadcast', 'label' => '일대여'],
            ['key' => 'monthly', 'type_key' => 'broadcast', 'label' => '월대여'],
            ['key' => 'shop', 'type_key' => 'product_inquiry', 'label' => '쇼핑몰'],
        ];

        foreach ($seed as $i => $row) {
            DB::table('work_types')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'type_key' => $row['type_key'],
                    'label' => $row['label'],
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => DB::table('work_types')->where('key', $row['key'])->value('created_at') ?? now(),
                ]
            );
        }

        // 기획안에 없는 레거시 작업 유형은 비활성화 (기존 프로젝트 라벨 표시는 유지)
        DB::table('work_types')
            ->whereNotIn('key', array_column($seed, 'key'))
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('work_types', function (Blueprint $table) {
            $table->dropColumn('type_key');
        });
    }
};

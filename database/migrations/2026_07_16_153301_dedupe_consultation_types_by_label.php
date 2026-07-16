<?php

use App\Models\ConsultationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 라벨이 같은 프로젝트 유형 중복 정리 (예: 시드 전에 수동 추가된 '방송룸' + 시드 'broadcast').
     * 기본(DEFAULTS) 키를 대표로 남기고, 중복 키를 쓰던 프로젝트는 대표 키로 리매핑 후 중복 행 삭제.
     */
    public function up(): void
    {
        $rows = DB::table('consultation_types')->orderBy('id')->get();

        foreach ($rows->groupBy('label') as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $keep = $group->first(fn ($r) => array_key_exists($r->key, ConsultationType::DEFAULTS))
                ?? $group->sortBy('id')->first();

            foreach ($group as $r) {
                if ($r->id === $keep->id) {
                    continue;
                }
                DB::table('projects')->where('project_type', $r->key)->update(['project_type' => $keep->key]);
                DB::table('consultation_types')->where('id', $r->id)->delete();
            }
        }
    }

    /** 데이터 정리 마이그레이션 — 되돌릴 원본이 없어 비가역 (forward-fix) */
    public function down(): void
    {
        // no-op
    }
};

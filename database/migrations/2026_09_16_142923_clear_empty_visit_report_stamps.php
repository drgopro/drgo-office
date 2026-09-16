<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 내용이 없는 방문보고의 작성 시각 제거.
 * 백필이 '<p></p>' 같은 HTML상 빈 보고서에도 시각을 찍어, 대시보드 최근 방문보고와
 * '방문보고 작성' 필터에 미작성 건이 섞이던 문제 — 텍스트/이미지가 전혀 없는 건만 해제한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')
            ->select(['id', 'visit_report'])
            ->whereNotNull('visit_report_updated_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $ids = [];
                foreach ($rows as $row) {
                    $html = (string) ($row->visit_report ?? '');
                    if (trim(strip_tags($html)) === '' && ! str_contains($html, '<img')) {
                        $ids[] = $row->id;
                    }
                }
                if ($ids !== []) {
                    DB::table('projects')->whereIn('id', $ids)->update(['visit_report_updated_at' => null]);
                }
            });
    }

    public function down(): void
    {
        // 빈 보고서의 시각 해제는 되돌릴 필요 없음 — 정리 성격의 전진 마이그레이션
    }
};

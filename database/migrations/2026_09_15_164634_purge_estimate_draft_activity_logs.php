<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 견적서 자동 임시저장(draft/draft_saved_at)이 남긴 기존 활동 로그 정리.
 * 원본 JSON이 통째로 기록돼 수정 로그 모달을 뒤덮던 항목 — 이후 기록은 트레이트에서 이미 차단됨.
 * draft 관련 키만 담긴 로그만 지우고, 실제 변경이 섞인 로그는 보존한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_logs')
            ->where('loggable_type', 'App\\Models\\Estimate')
            ->where('action', 'update')
            ->where(function ($q) {
                $q->where('changes', 'like', '%"draft"%')
                    ->orWhere('changes', 'like', '%"draft_saved_at"%');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $ids = [];
                foreach ($rows as $row) {
                    $keys = array_keys(json_decode($row->changes ?? '[]', true) ?: []);
                    if ($keys === []) {
                        continue;
                    }
                    // draft/draft_saved_at 외의 키가 하나라도 있으면 보존 (예: 상태값이 'draft'였던 과거 로그)
                    $others = array_diff($keys, ['draft', 'draft_saved_at']);
                    if ($others === []) {
                        $ids[] = $row->id;
                    }
                }
                if ($ids !== []) {
                    DB::table('activity_logs')->whereIn('id', $ids)->delete();
                }
            });
    }

    public function down(): void
    {
        // 로그 삭제는 복구 불가 — 정리 성격의 전진 마이그레이션
    }
};

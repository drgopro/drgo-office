<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 견적서 연동 시 자동 생성되던 '미수 청구' 폐기에 따른 기존 데이터 정리.
     * 연동만으로 프로젝트에 견적 금액이 '잔금'으로 잡히던 자동 청구 중
     * 입금 이력이 전혀 없는 미입금 건만 삭제한다 — 입금이 시작됐거나 완료된
     * 청구, 수기 청구(estimate_id 없음)는 실제 돈의 기록이므로 보존.
     */
    public function up(): void
    {
        if (! Schema::hasTable('project_billings') || ! Schema::hasColumn('project_billings', 'estimate_id')) {
            return;
        }

        DB::table('project_billings')
            ->whereNotNull('estimate_id')
            ->where('status', 'unpaid')
            ->whereNotIn('id', DB::table('project_payments')
                ->whereNotNull('billing_id')->select('billing_id'))
            ->delete();
    }

    public function down(): void
    {
        // 파생 데이터 삭제 — 복원 불필요 (견적서 저장 시에도 같은 정리가 반복 적용됨)
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 새 유형별 flow에서 사라진 단계에 있는 기존 프로젝트 리매핑 (DML)
     * - 상품(product_inquiry): 견적서전달(estimate)/결제대기(payment) → 처리(visit)
     * - 원격(remote): 견적/계약(estimate) → 견적/결제(payment)
     * - 문의(inquiry): 처리 중(visit) → 상담(consulting)
     */
    public function up(): void
    {
        DB::table('projects')->where('project_type', 'product_inquiry')
            ->whereIn('stage', ['estimate', 'payment'])
            ->update(['stage' => 'visit']);

        DB::table('projects')->where('project_type', 'remote')
            ->where('stage', 'estimate')
            ->update(['stage' => 'payment']);

        DB::table('projects')->where('project_type', 'inquiry')
            ->where('stage', 'visit')
            ->update(['stage' => 'consulting']);
    }

    public function down(): void
    {
        // 리매핑 이전 단계 구분이 소실되므로 복원하지 않음 (forward-fix)
    }
};

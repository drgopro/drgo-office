<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1) consultation_types: product_inquiry (상품 문의)
        if (Schema::hasTable('consultation_types')
            && ! DB::table('consultation_types')->where('key', 'product_inquiry')->exists()) {
            $nextOrder = (int) (DB::table('consultation_types')->max('sort_order') ?? 0) + 1;
            DB::table('consultation_types')->insert([
                'key' => 'product_inquiry',
                'label' => '상품 문의',
                'sort_order' => $nextOrder,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2) work_types: paid (단순 결제) — 사용자가 이미 추가한 경우 건너뜀
        if (Schema::hasTable('work_types')
            && ! DB::table('work_types')->where('key', 'paid')->exists()) {
            $nextOrder = (int) (DB::table('work_types')->max('sort_order') ?? 0) + 1;
            DB::table('work_types')->insert([
                'key' => 'paid',
                'label' => '단순 결제',
                'scale_keys' => null, // 모든 규모
                'sort_order' => $nextOrder,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('consultation_types')) {
            DB::table('consultation_types')->where('key', 'product_inquiry')->delete();
        }
        if (Schema::hasTable('work_types')) {
            DB::table('work_types')->where('key', 'paid')->delete();
        }
    }
};

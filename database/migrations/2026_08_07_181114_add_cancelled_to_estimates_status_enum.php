<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 견적서 상태 ENUM에 'cancelled'(결제 취소) 추가 — 환불/승인취소된 견적서 표시용.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('temp','created','editing','completed','issued','paid','hold','cancelled') DEFAULT 'temp'");
        }
    }

    public function down(): void
    {
        DB::table('estimates')->where('status', 'cancelled')->update(['status' => 'completed']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('temp','created','editing','completed','issued','paid','hold') DEFAULT 'temp'");
        }
    }
};

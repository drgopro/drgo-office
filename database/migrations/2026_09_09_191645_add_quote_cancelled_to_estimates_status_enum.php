<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 견적서 상태 ENUM에 'quote_cancelled'(견적 취소) 추가 — 결제 취소(cancelled)와 별개로,
     * 진행이 무산된 견적서 표시용. 프로젝트 취소 시 연동 견적서가 자동으로 이 상태가 된다.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('temp','created','editing','completed','issued','paid','hold','cancelled','quote_cancelled') DEFAULT 'temp'");
        }
    }

    public function down(): void
    {
        DB::table('estimates')->where('status', 'quote_cancelled')->update(['status' => 'hold']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('temp','created','editing','completed','issued','paid','hold','cancelled') DEFAULT 'temp'");
        }
    }
};

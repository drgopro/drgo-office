<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 견적서 상태 ENUM에 'issued'(발행 완료) 추가.
     * MySQL은 ENUM 컬럼이라 값 목록을 직접 확장해야 함 (sqlite는 string이라 불필요).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('temp','created','editing','completed','issued','paid','hold') DEFAULT 'temp'");
        }
    }

    public function down(): void
    {
        DB::table('estimates')->where('status', 'issued')->update(['status' => 'completed']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM('temp','created','editing','completed','paid','hold') DEFAULT 'temp'");
        }
    }
};

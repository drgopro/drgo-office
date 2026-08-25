<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 견적서 표시 번호(estimate_no) — DB 자동증가 id와 분리.
 * 생성(temp) 시점에는 번호를 발급하지 않고 첫 실제 저장 때 max+1로 발급해,
 * 만들고 버린 견적서 때문에 번호가 건너뛰지 않는다.
 * unique 제약이 동시 저장의 중복 번호를 막는다 (충돌 시 앱에서 재시도).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('estimates', 'estimate_no')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->unsignedInteger('estimate_no')->nullable()->after('id')->unique();
            });
        }

        // 기존 견적서는 지금까지 쓰던 번호(id)를 그대로 표시 번호로 백필
        DB::table('estimates')->whereNull('estimate_no')->where('status', '!=', 'temp')
            ->update(['estimate_no' => DB::raw('id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('estimates', 'estimate_no')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->dropColumn('estimate_no');
            });
        }
    }
};

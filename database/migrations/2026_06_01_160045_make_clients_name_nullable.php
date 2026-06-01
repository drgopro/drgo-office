<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 닉네임이 필수키이므로 실명(name)은 선택 입력으로 변경
        Schema::table('clients', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->comment('실명 (선택)')->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // 롤백 시 NULL이 있으면 실패하므로 빈문자로 백필
            DB::statement("UPDATE clients SET name='' WHERE name IS NULL");
            $table->string('name', 100)->nullable(false)->comment('실명')->change();
        });
    }
};

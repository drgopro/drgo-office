<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 마케팅 활용 동의를 3단계로 확장 — null=미확인, true=동의, false=거부.
 * 기존 false는 '거부'가 아니라 '미확인'이었으므로 null로 옮긴다.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('marketing_consent')->nullable()->default(null)->change();
        });

        DB::table('projects')->where('marketing_consent', false)->update(['marketing_consent' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('projects')->whereNull('marketing_consent')->update(['marketing_consent' => false]);

        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('marketing_consent')->default(false)->nullable(false)->change();
        });
    }
};

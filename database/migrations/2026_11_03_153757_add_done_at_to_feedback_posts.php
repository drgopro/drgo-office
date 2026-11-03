<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 피드백 완료 처리 시각 — 완료 글을 완료된 순으로 정렬하기 위함. 기존 완료 글은 updated_at으로 소급 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_posts', function (Blueprint $table) {
            $table->timestamp('done_at')->nullable()->after('reject_reason')
                ->comment('완료 처리 시각 (status=done 전환 시점)');
        });

        DB::table('feedback_posts')->where('status', 'done')->update(['done_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('feedback_posts', function (Blueprint $table) {
            $table->dropColumn('done_at');
        });
    }
};

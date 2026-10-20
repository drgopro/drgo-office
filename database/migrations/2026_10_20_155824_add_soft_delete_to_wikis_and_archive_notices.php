<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 위키 소프트 삭제 도입 + 공지 이전 마무리.
 * - wikis.deleted_at 추가 — 삭제된 게시물은 관리자 전용 '삭제된 게시물' 보관함에서 복구/영구 삭제
 * - 새 공지사항 게시판으로 복사 이관된 기존 위키 공지(type=notice)는 보관함으로 이전(소프트 삭제)
 *   ※ 복사 자체는 직전 마이그레이션(migrate_wiki_notices_to_notices)에서 수행
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wikis', 'deleted_at')) {
            Schema::table('wikis', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        DB::table('wikis')->where('type', 'notice')->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        DB::table('wikis')->where('type', 'notice')->update(['deleted_at' => null]);
        Schema::table('wikis', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};

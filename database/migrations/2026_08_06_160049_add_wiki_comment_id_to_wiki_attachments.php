<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 회의록 댓글 파일 첨부 — 기존 wiki_attachments를 재사용하고
     * 댓글 소속만 표시한다 (댓글 삭제 시 행도 함께 삭제).
     */
    public function up(): void
    {
        Schema::table('wiki_attachments', function (Blueprint $table) {
            $table->foreignId('wiki_comment_id')->nullable()->after('wiki_id')
                ->constrained('wiki_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wiki_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wiki_comment_id');
        });
    }
};

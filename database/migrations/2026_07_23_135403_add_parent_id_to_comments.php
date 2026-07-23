<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 피드백·위키 댓글 대댓글 (1뎁스) — 부모 삭제 시 대댓글도 함께 삭제 */
    public function up(): void
    {
        Schema::table('feedback_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('feedback_post_id')
                ->constrained('feedback_comments')->cascadeOnDelete();
        });
        Schema::table('wiki_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('wiki_id')
                ->constrained('wiki_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feedback_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
        Schema::table('wiki_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 위키 댓글 — 회의록(meeting) 유형 게시물 전용 */
    public function up(): void
    {
        Schema::create('wiki_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_comments');
    }
};

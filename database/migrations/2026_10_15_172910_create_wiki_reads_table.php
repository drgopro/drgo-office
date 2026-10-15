<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 위키/공지 읽음 확인 — 문서를 연 사용자와 시각 기록 (문서당 사용자 1행) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_id')->constrained('wikis')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['wiki_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::drop('wiki_reads');
    }
};

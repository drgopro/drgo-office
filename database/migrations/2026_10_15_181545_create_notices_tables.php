<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 공지사항 게시판 — 위키에서 분리한 독립 게시판 (연결도/템플릿/회의록 댓글 없이 간소화).
 * 카테고리 트리·첨부·읽음 확인은 위키와 동일 구조, 고정(핀)에 유지 기간(pinned_until) 추가.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('notice_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('parent_id');
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->foreignId('category_id')->nullable()->constrained('notice_categories')->nullOnDelete();
            $table->longText('content');
            $table->boolean('is_pinned')->default(false);
            $table->date('pinned_until')->nullable(); // 상단 고정 유지 기한 — 지나면 일반 글처럼 정렬
            $table->boolean('is_draft')->default(false);
            $table->json('allowed_team_ids')->nullable(); // 열람 허용 팀 (비면 전체 공개)
            $table->integer('sort_order')->default(0); // 수동 정렬 (관리자)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('is_pinned');
            $table->index('category_id');
        });

        Schema::create('notice_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained('notices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notice_attachments');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('notice_categories');
    }
};

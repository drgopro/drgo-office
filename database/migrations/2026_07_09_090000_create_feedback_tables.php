<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->index()->comment('bug | feature');
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('page', 50)->nullable()->comment('제보 대상 페이지 (메뉴 라벨)');
            $table->string('status', 15)->default('waiting')->index()->comment('waiting|reviewing|hold|done|rejected');
            $table->string('reject_reason', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('feedback_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_post_id')->constrained('feedback_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_post_id')->constrained('feedback_posts')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_attachments');
        Schema::dropIfExists('feedback_comments');
        Schema::dropIfExists('feedback_posts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 위키 템플릿 — 글 작성 시 불러올 수 있는 미리 만든 글 서식(HTML) */
    public function up(): void
    {
        Schema::create('wiki_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->longText('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_templates');
    }
};

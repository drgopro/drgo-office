<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visit_report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->longText('content')->nullable()->comment('HTML 본문 (Tiptap 출력)');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false)->comment('새 보고서 기본 템플릿 여부');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_report_templates');
    }
};

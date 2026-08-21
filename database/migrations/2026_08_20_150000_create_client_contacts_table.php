<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 의뢰자 관계자(매니저/실장 등) — 의뢰자당 최대 10명 (컨트롤러에서 제한).
     * 관계자의 이름/연락처는 의뢰자 검색에 함께 매칭된다.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_contacts')) {
            return;
        }
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 30)->nullable();
            $table->string('relation', 50)->nullable()->comment('관계 — 매니저/실장/소속사 등 자유 입력');
            $table->string('memo', 500)->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};

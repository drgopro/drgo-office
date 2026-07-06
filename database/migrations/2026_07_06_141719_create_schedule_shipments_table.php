<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->string('carrier', 30);              // kr.cjlogistics 등 delivery-tracker carrier id
            $table->string('tracking_no', 40);
            $table->string('status', 20)->default('pending'); // pending | in_transit | delivered | error
            $table->string('last_event', 200)->nullable();    // 마지막 배송 스텝 텍스트
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('checked_at')->nullable();      // 마지막 추적 조회 시각
            $table->json('raw')->nullable();                  // 추적 API 원본 응답 (디버그용)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['schedule_id']);
            $table->index(['status']);
            $table->unique(['schedule_id', 'carrier', 'tracking_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shipments');
    }
};

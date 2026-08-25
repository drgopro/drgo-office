<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 주문 내역 — 사무실 비품/간식 등 견적서와 무관한 직접 주문 건 (항목은 JSON 스냅샷) */
    public function up(): void
    {
        if (Schema::hasTable('office_orders')) {
            return;
        }
        Schema::create('office_orders', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->json('items'); // [{name, qty, purchase_source, memo}]
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_orders');
    }
};

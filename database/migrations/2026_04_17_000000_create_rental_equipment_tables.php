<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 대여 장비 현황판 전용 테이블.
     *
     * - rental_targets: 장비 사용 대상(창고/스튜디오/사람 등)
     * - rental_items: 대여용 장비 (Product와 분리)
     * - rental_logs: 변경 이력
     */
    public function up(): void
    {
        Schema::create('rental_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('serial', 100)->nullable()->comment('제품 시리얼 번호');
            $table->string('category', 100)->nullable();
            $table->text('components')->nullable()->comment('제품 구성');
            $table->text('description')->nullable();
            $table->foreignId('current_target_id')
                ->nullable()
                ->constrained('rental_targets')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('current_target_id');
        });

        Schema::create('rental_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('rental_items')->nullOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('rental_targets')->nullOnDelete();
            $table->string('action', 50);
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_logs');
        Schema::dropIfExists('rental_items');
        Schema::dropIfExists('rental_targets');
    }
};

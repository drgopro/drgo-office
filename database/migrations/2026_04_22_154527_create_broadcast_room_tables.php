<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 방송룸 월 계약
        Schema::create('broadcast_room_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedInteger('monthly_fee')->default(0);
            $table->string('status', 20)->default('active'); // active | terminated
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
            $table->index('start_date');
        });

        // 방송룸 시간 대여
        Schema::create('broadcast_room_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('used_date');
            $table->decimal('hours', 5, 2)->default(0);
            $table->unsignedInteger('fee')->default(0);
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('used_date');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_room_usages');
        Schema::dropIfExists('broadcast_room_contracts');
    }
};

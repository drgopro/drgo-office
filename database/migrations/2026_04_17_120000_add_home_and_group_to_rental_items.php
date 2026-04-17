<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        Schema::table('rental_items', function (Blueprint $table) {
            $table->foreignId('home_target_id')
                ->nullable()
                ->after('current_target_id')
                ->constrained('rental_targets')
                ->nullOnDelete()
                ->comment('원래 위치 (반납 시 자동 복귀 대상)');
            $table->foreignId('group_id')
                ->nullable()
                ->after('home_target_id')
                ->constrained('rental_groups')
                ->nullOnDelete();

            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropForeign(['home_target_id']);
            $table->dropForeign(['group_id']);
            $table->dropIndex(['group_id']);
            $table->dropColumn(['home_target_id', 'group_id']);
        });

        Schema::dropIfExists('rental_groups');
    }
};

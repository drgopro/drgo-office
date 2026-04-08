<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20)->default('update');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['schedule_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_changes');
    }
};

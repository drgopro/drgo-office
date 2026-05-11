<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique(); // gold/teal/blue/red/green/purple
            $table->string('label', 50);
            $table->string('color', 9);       // hex bg
            $table->string('text_color', 9);  // hex text on bg
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('calendar_categories')->insert([
            ['key' => 'gold', 'label' => '방문의뢰', 'color' => '#c8a870', 'text_color' => '#1a1207', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'teal', 'label' => '원격/방송룸', 'color' => '#e8894a', 'text_color' => '#1a0a00', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'blue', 'label' => '사내업무', 'color' => '#7aaec8', 'text_color' => '#061825', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'red', 'label' => '휴가/개인', 'color' => '#c87070', 'text_color' => '#200808', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'green', 'label' => '촬영/스튜디오', 'color' => '#70c870', 'text_color' => '#08200a', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'purple', 'label' => '미팅/내방', 'color' => '#9b70c8', 'text_color' => '#f0eaff', 'sort_order' => 6, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_categories');
    }
};

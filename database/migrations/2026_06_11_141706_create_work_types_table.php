<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->json('scale_keys')->nullable()
                ->comment('적용되는 규모(client_scale) 키 배열. NULL이면 모든 규모에 노출');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 기본 9종 시드
        $defaults = [
            ['key' => 'setup', 'label' => '세팅', 'scale_keys' => ['personal', 'studio', 'corporate'], 'sort_order' => 1],
            ['key' => 'remote', 'label' => '원격', 'scale_keys' => ['personal'], 'sort_order' => 2],
            ['key' => 'survey', 'label' => '답사', 'scale_keys' => ['studio', 'corporate'], 'sort_order' => 3],
            ['key' => 'filming', 'label' => '촬영중계', 'scale_keys' => ['personal', 'studio', 'corporate'], 'sort_order' => 4],
            ['key' => 'design', 'label' => '디자인', 'scale_keys' => ['personal', 'studio', 'corporate'], 'sort_order' => 5],
            ['key' => 'as', 'label' => 'A/S', 'scale_keys' => ['personal', 'studio', 'corporate'], 'sort_order' => 6],
            ['key' => 'dispatch', 'label' => '파견', 'scale_keys' => ['studio'], 'sort_order' => 7],
            ['key' => 'monthly', 'label' => '월 계약', 'scale_keys' => ['rental', 'broadcast_room'], 'sort_order' => 8],
            ['key' => 'hourly', 'label' => '시간 대여', 'scale_keys' => ['broadcast_room'], 'sort_order' => 9],
        ];
        $now = now();
        foreach ($defaults as $d) {
            DB::table('work_types')->insert([
                'key' => $d['key'],
                'label' => $d['label'],
                'scale_keys' => json_encode($d['scale_keys']),
                'sort_order' => $d['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_types');
    }
};

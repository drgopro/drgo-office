<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 장비 구성품 구조화 — [{"name":"전원 케이블","left_target_id":null}, ...]
     * left_target_id: null=본체와 함께, 값=이동 시 남겨진 위치(마지막 위치, Red 표시).
     * 기존 수기 textarea(components)는 줄/쉼표 단위로 분해해 백필한다.
     */
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->json('component_items')->nullable()->after('components');
        });

        foreach (DB::table('rental_items')->whereNotNull('components')->get(['id', 'components']) as $row) {
            $names = collect(preg_split('/[\r\n,]+/', (string) $row->components))
                ->map(fn ($s) => trim($s))->filter()->unique()->values();
            if ($names->isNotEmpty()) {
                DB::table('rental_items')->where('id', $row->id)->update([
                    'component_items' => json_encode(
                        $names->map(fn ($n) => ['name' => $n, 'left_target_id' => null])->all(),
                        JSON_UNESCAPED_UNICODE
                    ),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropColumn('component_items');
        });
    }
};

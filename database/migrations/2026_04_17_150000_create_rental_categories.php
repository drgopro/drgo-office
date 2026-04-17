<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        Schema::table('rental_items', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('rental_categories')
                ->nullOnDelete();

            $table->index('category_id');
        });

        // 기존 string category 값들을 카테고리 테이블로 이행
        $existing = DB::table('rental_items')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        $now = now();
        $map = [];
        $sort = 0;
        foreach ($existing as $name) {
            $id = DB::table('rental_categories')->insertGetId([
                'name' => $name,
                'sort_order' => ++$sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$name] = $id;
        }

        foreach ($map as $name => $id) {
            DB::table('rental_items')
                ->where('category', $name)
                ->update(['category_id' => $id]);
        }

        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('serial');
        });

        // 카테고리명 복원
        $rows = DB::table('rental_items')
            ->whereNotNull('category_id')
            ->get(['id', 'category_id']);

        $names = DB::table('rental_categories')->pluck('name', 'id');

        foreach ($rows as $r) {
            if (isset($names[$r->category_id])) {
                DB::table('rental_items')
                    ->where('id', $r->id)
                    ->update(['category' => $names[$r->category_id]]);
            }
        }

        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('rental_categories');
    }
};

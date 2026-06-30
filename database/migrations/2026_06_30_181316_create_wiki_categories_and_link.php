<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wiki_categories')) {
            Schema::create('wiki_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('wiki_categories')->nullOnDelete();
                $table->string('name', 100);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index('parent_id');
            });
        }

        if (! Schema::hasColumn('wikis', 'category_id')) {
            Schema::table('wikis', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->after('category')->constrained('wiki_categories')->nullOnDelete();
            });
        }

        // 기존 distinct 카테고리 문자열 → 최상위 노드로 시드 후 wikis.category_id 매핑
        $names = DB::table('wikis')->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category');
        $order = 0;
        foreach ($names as $name) {
            $existing = DB::table('wiki_categories')->whereNull('parent_id')->where('name', $name)->first();
            $id = $existing->id ?? DB::table('wiki_categories')->insertGetId([
                'parent_id' => null, 'name' => $name, 'sort_order' => $order++,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('wikis')->where('category', $name)->whereNull('category_id')->update(['category_id' => $id]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wikis', 'category_id')) {
            Schema::table('wikis', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }
        Schema::dropIfExists('wiki_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 제품의 표시용 카테고리명 재동기화 — 카테고리명 변경이 제품에 전파되지 않던
     * 시기에 어긋난 이름을 실제 카테고리명으로 일괄 정정한다. (이후에는 이름
     * 변경 시 자동 전파되므로 1회 백필)
     */
    public function up(): void
    {
        $names = DB::table('product_categories')->pluck('name', 'id');
        DB::table('products')
            ->whereNotNull('category_id')
            ->select('id', 'category_id', 'category')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($names) {
                foreach ($rows as $row) {
                    $name = $names[$row->category_id] ?? null;
                    if ($name !== null && $name !== $row->category) {
                        DB::table('products')->where('id', $row->id)->update(['category' => $name]);
                    }
                }
            });
    }

    public function down(): void
    {
        // 표시명 정정이므로 되돌릴 필요 없음
    }
};

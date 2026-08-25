<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 빌더 자동 임시저장 스냅샷 — 정식 저장과 별개, 저장 시 비워진다 */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (! Schema::hasColumn('estimates', 'draft')) {
                $table->json('draft')->nullable();
            }
            if (! Schema::hasColumn('estimates', 'draft_saved_at')) {
                $table->timestamp('draft_saved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            foreach (['draft', 'draft_saved_at'] as $col) {
                if (Schema::hasColumn('estimates', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

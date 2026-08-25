<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 견적서 제목 — 빌더에서 입력, 출력물 상단 헤더에 표시 */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (! Schema::hasColumn('estimates', 'title')) {
                $table->string('title', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};

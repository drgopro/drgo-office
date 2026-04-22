<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            // { setup: 0, product: 0, labor: 0, dispatch: 0, rush: 0, other: 0 }
            $table->json('category_breakdown')->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('category_breakdown');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wikis', function (Blueprint $table) {
            $table->json('diagram_data')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('wikis', function (Blueprint $table) {
            $table->dropColumn('diagram_data');
        });
    }
};

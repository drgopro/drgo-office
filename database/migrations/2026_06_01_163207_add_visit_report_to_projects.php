<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'visit_report')) {
                $table->longText('visit_report')->nullable()->after('memo')
                    ->comment('완료 후 방문 보고서 (HTML rich text)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'visit_report')) {
                $table->dropColumn('visit_report');
            }
        });
    }
};

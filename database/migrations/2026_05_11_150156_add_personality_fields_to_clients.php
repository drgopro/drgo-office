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
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'platform_etc')) {
                $table->string('platform_etc', 100)->nullable()->after('platforms');
            }
            if (! Schema::hasColumn('clients', 'topic_etc')) {
                $table->string('topic_etc', 100)->nullable()->after('content_types');
            }
            if (! Schema::hasColumn('clients', 'personality')) {
                $table->string('personality', 500)->nullable();
            }
            if (! Schema::hasColumn('clients', 'budget_style')) {
                $table->string('budget_style', 500)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['platform_etc', 'topic_etc', 'personality', 'budget_style']);
        });
    }
};

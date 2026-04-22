<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('inflow_source', 20)->nullable()->after('content_types');
            $table->string('client_type', 20)->nullable()->after('inflow_source');
            $table->index('inflow_source');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['inflow_source']);
            $table->dropColumn(['inflow_source', 'client_type']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
            $table->foreignId('client_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
            $table->foreignId('client_id')->nullable(false)->change();
        });
    }
};

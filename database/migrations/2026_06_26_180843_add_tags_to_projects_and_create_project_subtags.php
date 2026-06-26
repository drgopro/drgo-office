<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'tags')) {
                $table->json('tags')->nullable();
            }
        });

        if (! Schema::hasTable('project_subtags')) {
            Schema::create('project_subtags', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60)->unique();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_subtags');
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'tags')) {
                $table->dropColumn('tags');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demo_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('demo_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('client_name', 100)->nullable();
            $table->string('requester_type', 30)->nullable();
            $table->string('project_type', 30);
            $table->string('work_type', 60)->nullable();
            $table->json('tags')->nullable();
            $table->string('free_name', 200)->nullable();
            $table->string('stage', 30)->default('consult');
            $table->string('status', 20)->default('active');
            $table->string('cancel_reason', 100)->nullable();
            $table->json('billing')->nullable();
            $table->json('stage_data')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['project_type', 'status']);
        });

        $tags = ['이사세팅', '스튜디오세팅', '처음세팅', '세팅개선'];
        $now = now();
        foreach ($tags as $i => $name) {
            DB::table('demo_tags')->insert([
                'name' => $name, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_projects');
        Schema::dropIfExists('demo_tags');
    }
};

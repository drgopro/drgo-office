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
        Schema::table('demo_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('demo_projects', 'overview')) {
                $table->text('overview')->nullable()->after('free_name');
            }
            if (! Schema::hasColumn('demo_projects', 'client_phone')) {
                $table->string('client_phone', 50)->nullable()->after('client_name');
            }
            if (! Schema::hasColumn('demo_projects', 'client_address')) {
                $table->string('client_address', 300)->nullable()->after('client_phone');
            }
        });

        Schema::create('demo_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_project_id')->constrained('demo_projects')->cascadeOnDelete();
            $table->string('consult_type', 30)->nullable();
            $table->text('content')->nullable();
            $table->string('result', 30)->nullable();
            $table->date('consulted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_project_id')->constrained('demo_projects')->cascadeOnDelete();
            $table->text('content');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_feedbacks');
        Schema::dropIfExists('demo_consultations');
        Schema::table('demo_projects', function (Blueprint $table) {
            foreach (['overview', 'client_phone', 'client_address'] as $c) {
                if (Schema::hasColumn('demo_projects', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};

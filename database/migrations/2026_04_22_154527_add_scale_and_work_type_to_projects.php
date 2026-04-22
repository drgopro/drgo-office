<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('client_scale', 20)->nullable()->after('project_type');
            $table->string('work_type', 20)->nullable()->after('client_scale');
            $table->index('client_scale');
            $table->index('work_type');
        });

        // 기존 데이터 자동 매핑 (B안): project_type 기반
        $mapping = [
            'visit' => ['personal', 'setup'],
            'remote' => ['personal', 'remote'],
            'as' => ['personal', 'as'],
            'design' => ['personal', 'design'],
            'inquiry' => ['personal', 'setup'],
            'troubleshoot' => ['personal', 'as'],
        ];
        foreach ($mapping as $type => [$scale, $work]) {
            DB::table('projects')->where('project_type', $type)->update([
                'client_scale' => $scale,
                'work_type' => $work,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['client_scale']);
            $table->dropIndex(['work_type']);
            $table->dropColumn(['client_scale', 'work_type']);
        });
    }
};

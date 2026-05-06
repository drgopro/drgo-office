<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_changes', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('changes')
                ->comment('수정/삭제 시 사용자가 입력한 사유');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_changes', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};

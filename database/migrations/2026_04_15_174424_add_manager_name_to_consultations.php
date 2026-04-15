<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('manager_name', 100)->nullable()->after('consultant_id')->comment('담당자 (수기 입력)');
            $table->foreignId('author_user_id')->nullable()->after('manager_name')->constrained('users')->nullOnDelete()->comment('작성자 (자동)');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['author_user_id']);
            $table->dropColumn(['manager_name', 'author_user_id']);
        });
    }
};

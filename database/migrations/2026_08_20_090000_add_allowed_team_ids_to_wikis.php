<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wikis', 'allowed_team_ids')) {
            Schema::table('wikis', function (Blueprint $table) {
                // null = 전체 공개, 값이 있으면 해당 팀(+작성자·관리자)만 열람 가능
                $table->json('allowed_team_ids')->nullable()->after('is_draft');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wikis', 'allowed_team_ids')) {
            Schema::table('wikis', function (Blueprint $table) {
                $table->dropColumn('allowed_team_ids');
            });
        }
    }
};

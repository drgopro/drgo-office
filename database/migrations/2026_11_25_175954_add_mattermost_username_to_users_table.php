<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mattermost 아이디 — 알림에서 담당자를 @아이디로 태그해 개인 알림이 가게 한다 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'mattermost_username')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('mattermost_username', 64)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mattermost_username');
        });
    }
};

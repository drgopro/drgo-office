<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            // 견적서 검토 상태 — {fingerprint, keys, answers:{key:{type,choice|reason,note,by,at}}, done, reviewer, reviewed_at}
            $table->json('review_state')->nullable()->after('internal_memo');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('review_state');
        });
    }
};

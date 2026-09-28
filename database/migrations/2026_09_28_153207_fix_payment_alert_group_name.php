<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 결제완료 알림 톡방 이름 교정 — 채널톡에 실제로 만들어진 그룹은 '견적서결제알림'
     * (시드했던 '견적서결제'는 존재하지 않아 발송 422). 사용자가 이미 다른 값으로
     * 바꿨다면 건드리지 않는다.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'payment_alert_group')
            ->where('value', '견적서결제')
            ->update(['value' => '견적서결제알림', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'payment_alert_group')
            ->where('value', '견적서결제알림')
            ->update(['value' => '견적서결제', 'updated_at' => now()]);
    }
};

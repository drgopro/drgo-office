<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 결제완료 알림 톡방 기본값 등록 — 채널톡에 만들어 둔 '견적서결제' 그룹.
     * 이미 값이 저장돼 있으면 건드리지 않는다 (관리 > 설정 > 결제 알림에서 변경 가능).
     */
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'payment_alert_group')->first();
        if (! $exists) {
            DB::table('settings')->insert([
                'key' => 'payment_alert_group',
                'value' => '견적서결제',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif (trim((string) $exists->value) === '') {
            DB::table('settings')->where('key', 'payment_alert_group')->update([
                'value' => '견적서결제',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'payment_alert_group')->where('value', '견적서결제')->delete();
    }
};

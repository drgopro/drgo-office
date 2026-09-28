<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 게시물 알림 톡방 기본값 등록 — 채널톡에 만들어 둔 '새게시물알림' 그룹.
     * drgo.pro 게시판 새 글/댓글 + 위키 새 글 알림이 이 방으로 발송된다
     * (기존에는 기본 팀챗 그룹으로 발송). 이미 값이 있으면 유지.
     */
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'post_alert_group')->first();
        if (! $exists) {
            DB::table('settings')->insert([
                'key' => 'post_alert_group',
                'value' => '새게시물알림',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif (trim((string) $exists->value) === '') {
            DB::table('settings')->where('key', 'post_alert_group')->update([
                'value' => '새게시물알림',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'post_alert_group')->where('value', '새게시물알림')->delete();
    }
};

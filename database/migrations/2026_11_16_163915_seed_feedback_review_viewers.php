<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEY = 'feedback_review_viewers';

    /**
     * 피드백 AI 검수 열람자 초기값 — 마스터는 항상 볼 수 있고, 그 외에는 이 목록의 사용자만 본다.
     * 요청에 따라 '이유진' 계정을 넣는다. 같은 이름이 없거나 여러 명이면 비워 두고 마스터가 피드백 화면에서 지정한다.
     */
    public function up(): void
    {
        if (DB::table('settings')->where('key', self::KEY)->exists()) {
            return; // 재실행 안전 — 이미 지정된 목록은 건드리지 않음
        }

        $ids = DB::table('users')->where('display_name', '이유진')->where('is_active', true)->pluck('id');

        DB::table('settings')->insert([
            'key' => self::KEY,
            'value' => json_encode($ids->count() === 1 ? $ids->map(fn ($id) => (int) $id)->all() : []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};

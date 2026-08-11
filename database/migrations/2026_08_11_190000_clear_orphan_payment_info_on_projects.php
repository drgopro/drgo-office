<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 유령 결제 정리 — 결제 트랜잭션을 삭제했지만 payment_info(JSON)가 남아
     * 캘린더 요약의 구버전 폴백이 '결제 1건'으로 표시하던 데이터를 청소한다.
     * recorded_at 키는 트랜잭션 도입 이후 기록에만 존재하므로, 이 키가 있는데
     * charge 행이 하나도 없으면 '기록 후 삭제된 결제'로 확정할 수 있다.
     * (트랜잭션 도입 이전의 순수 구버전 payment_info는 recorded_at이 없어 보존됨)
     */
    public function up(): void
    {
        $projects = DB::table('projects')
            ->whereNotNull('payment_info')
            ->get(['id', 'payment_info']);

        foreach ($projects as $row) {
            $info = json_decode((string) $row->payment_info, true);
            if (! is_array($info) || empty($info['recorded_at']) || (int) ($info['amount'] ?? 0) <= 0) {
                continue;
            }
            $hasCharge = DB::table('project_payments')
                ->where('project_id', $row->id)
                ->where('type', 'charge')
                ->exists();
            if (! $hasCharge) {
                DB::table('projects')->where('id', $row->id)->update(['payment_info' => null]);
            }
        }
    }

    public function down(): void
    {
        // 데이터 정리 마이그레이션 — 되돌릴 수 없음 (삭제된 결제의 잔재 제거)
    }
};

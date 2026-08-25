<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 캘린더 환불 표시(estimate_refund) 백필 — 표시 기능 배포 전에 기록된 환불이
     * 연동 일정에 반영되지 않아, 견적서가 연동된 모든 일정에 환불 합계를 한 번 계산해 넣는다.
     * 이후에는 환불 기록·일정 저장 시 자동 동기화된다.
     */
    public function up(): void
    {
        DB::table('schedules')
            ->whereNotNull('request_data')
            ->where('request_data', 'like', '%estimate_id%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $g = json_decode((string) $row->request_data, true);
                    if (! is_array($g) || empty($g['estimate_id'])) {
                        continue;
                    }
                    $estimate = DB::table('estimates')->where('id', (int) $g['estimate_id'])->first();
                    if (! $estimate) {
                        continue;
                    }
                    $items = json_decode((string) ($estimate->product_items ?? '[]'), true) ?: [];
                    $total = array_sum(array_map(fn ($i) => (int) ($i['refund_amount'] ?? 0), $items));

                    $new = $total > 0 ? number_format($total) : '';
                    if (($g['estimate_refund'] ?? '') === $new) {
                        continue;
                    }
                    if ($new === '') {
                        unset($g['estimate_refund']);
                    } else {
                        $g['estimate_refund'] = $new;
                    }
                    DB::table('schedules')->where('id', $row->id)
                        ->update(['request_data' => json_encode($g, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        // 표시용 키 백필이므로 되돌릴 필요 없음
    }
};

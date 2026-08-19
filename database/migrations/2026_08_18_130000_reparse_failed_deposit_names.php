<?php

use App\Services\DepositSmsParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 입금자명 파싱에 실패한 기존 입금 건을 개선된 파서로 재파싱 (법인명 등) */
    public function up(): void
    {
        $parser = new DepositSmsParser;
        foreach (DB::table('bank_deposits')->whereNull('depositor_name')->get(['id', 'raw_text']) as $row) {
            $parsed = $parser->parse((string) $row->raw_text);
            if ($parsed['depositor_name'] !== null) {
                DB::table('bank_deposits')->where('id', $row->id)
                    ->update(['depositor_name' => $parsed['depositor_name']]);
            }
        }
    }

    public function down(): void
    {
        // 재파싱 결과 되돌리기 불필요
    }
};

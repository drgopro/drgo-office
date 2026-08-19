<?php

use App\Services\DepositSmsParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 전체 입금 건의 입금자명을 개선된 파서로 재파싱 —
     * 구버전 파서가 괄호를 깎아 저장한 이름("주)OOO"), 안 닫힌 괄호("조신영(조신몽컴")를 원문에서 복구.
     * 파싱 결과가 null이면 기존 값 유지.
     */
    public function up(): void
    {
        $parser = new DepositSmsParser;
        foreach (DB::table('bank_deposits')->get(['id', 'raw_text', 'depositor_name']) as $row) {
            $parsed = $parser->parse((string) $row->raw_text);
            $name = $parsed['depositor_name'];
            if ($name !== null && $name !== $row->depositor_name) {
                DB::table('bank_deposits')->where('id', $row->id)->update(['depositor_name' => $name]);
            }
        }
    }

    public function down(): void
    {
        // 재파싱 결과 되돌리기 불필요
    }
};

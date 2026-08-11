<?php

use App\Services\DepositSmsParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_deposits', function (Blueprint $table) {
            $table->string('bank', 30)->nullable()->after('depositor_name'); // 입금 은행 (문자에서 인식, 예: 국민은행)
        });

        // 기존 데이터 백필 — 원문에서 은행 재인식
        $parser = new DepositSmsParser;
        foreach (DB::table('bank_deposits')->whereNull('bank')->get(['id', 'raw_text']) as $row) {
            if ($bank = $parser->parseBank((string) $row->raw_text)) {
                DB::table('bank_deposits')->where('id', $row->id)->update(['bank' => $bank]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bank_deposits', function (Blueprint $table) {
            $table->dropColumn('bank');
        });
    }
};

<?php

use App\Models\RequestItemPreset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * children을 map(분류 => 항목[])에서 [{name, items}] 배열로 변환.
     * MySQL JSON 타입이 객체 키를 재정렬해 분류 표시 순서가 뒤섞이던 문제 수정 —
     * 배열은 순서가 보존되며, 변환 시 기본 서비스→의뢰 서비스→컴퓨터→카메라/조명→오디오→기타 순으로 정렬.
     */
    public function up(): void
    {
        RequestItemPreset::query()->get()->each(function (RequestItemPreset $preset) {
            // 모델 accessor가 map → 정렬된 배열로 정규화한 값을 그대로 기록
            DB::table('request_item_presets')->where('id', $preset->id)->update([
                'children' => json_encode($preset->children, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function down(): void
    {
        // 정방향 데이터 정규화 — 배열 형태도 모델 accessor가 그대로 읽으므로 되돌릴 필요 없음
    }
};

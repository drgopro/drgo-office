<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationType extends Model
{
    protected $fillable = ['key', 'label', 'stages', 'sort_order', 'is_active'];

    protected $casts = [
        'stages' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @var array<string, string> 기본 셋 (마이그레이션/시드에 사용) */
    public const DEFAULTS = [
        'inquiry' => '문의',
        'remote' => '원격',
        'visit' => '방문',
        'store' => '내방',
        'filming' => '촬영',
        'design' => '제작',
        'rental' => '렌탈',
        'broadcast' => '방송룸',
        'product_inquiry' => '상품',
        'as' => 'A/S',
        'troubleshoot' => '문제 해결',
    ];

    /**
     * 유형별 기본 진행 단계 flow (기획 '유형별 진행 단계' 기준).
     * kind: payment(결제류 — 결제 모달·청구 체크), estimate(견적 모달), proposal(일정제안 모달),
     *       work(수행 단계), done(완료 확인), normal(단순 전환).
     *
     * @var array<string, array<int, array{code: string, label: string, kind: string}>>
     */
    public const DEFAULT_FLOWS = [
        'inquiry' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ],
        'remote' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'equipment', 'label' => '진단', 'kind' => 'normal'],
            ['code' => 'proposal', 'label' => '일정제안', 'kind' => 'proposal'],
            ['code' => 'payment', 'label' => '견적/결제', 'kind' => 'payment'],
            ['code' => 'visit', 'label' => '원격처리', 'kind' => 'work'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ],
        'visit' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'proposal', 'label' => '일정제안', 'kind' => 'proposal'],
            ['code' => 'estimate', 'label' => '견적/계약', 'kind' => 'estimate'],
            ['code' => 'payment', 'label' => '결제/예약', 'kind' => 'payment'],
            ['code' => 'visit', 'label' => '방문세팅', 'kind' => 'work'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ],
        'store' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'proposal', 'label' => '일정제안', 'kind' => 'proposal'],
            ['code' => 'payment', 'label' => '결제', 'kind' => 'payment'],
            ['code' => 'visit', 'label' => '내방', 'kind' => 'work'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ],
        'filming' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'proposal', 'label' => '일정제안', 'kind' => 'proposal'],
            ['code' => 'survey', 'label' => '사전답사', 'kind' => 'normal'],
            ['code' => 'estimate', 'label' => '견적/계약', 'kind' => 'estimate'],
            ['code' => 'payment', 'label' => '결제', 'kind' => 'payment'],
            ['code' => 'visit', 'label' => '촬영', 'kind' => 'work'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ],
        'design' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'estimate', 'label' => '견적/계약', 'kind' => 'estimate'],
            ['code' => 'payment', 'label' => '결제', 'kind' => 'payment'],
            ['code' => 'visit', 'label' => '제작/컨펌', 'kind' => 'work'],
            ['code' => 'delivery', 'label' => '납품', 'kind' => 'normal'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ],
        'rental' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'proposal', 'label' => '일정제안', 'kind' => 'proposal'],
            ['code' => 'estimate', 'label' => '견적', 'kind' => 'estimate'],
            ['code' => 'payment', 'label' => '결제/계약', 'kind' => 'payment'],
        ],
        'broadcast' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'proposal', 'label' => '일정제안/견적', 'kind' => 'estimate'],
            ['code' => 'payment', 'label' => '결제', 'kind' => 'payment'],
            ['code' => 'visit', 'label' => '이용', 'kind' => 'work'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ],
        'product_inquiry' => [
            ['code' => 'consulting', 'label' => '상담', 'kind' => 'normal'],
            ['code' => 'visit', 'label' => '처리', 'kind' => 'work'],
            ['code' => 'done', 'label' => '완료', 'kind' => 'done'],
        ],
        'as' => [
            ['code' => 'consulting', 'label' => 'AS 접수', 'kind' => 'normal'],
            ['code' => 'equipment', 'label' => '점검', 'kind' => 'normal'],
            ['code' => 'visit', 'label' => 'AS 진행', 'kind' => 'work'],
            ['code' => 'done', 'label' => '처리 완료', 'kind' => 'done'],
        ],
        'troubleshoot' => [
            ['code' => 'consulting', 'label' => '문의 접수', 'kind' => 'normal'],
            ['code' => 'equipment', 'label' => '진단', 'kind' => 'normal'],
            ['code' => 'visit', 'label' => '조치 진행', 'kind' => 'work'],
            ['code' => 'done', 'label' => '해결 완료', 'kind' => 'done'],
        ],
    ];

    /**
     * key=>label 매핑 (활성만, 정렬 적용). DB 비어있으면 DEFAULTS 사용.
     *
     * @return array<string, string>
     */
    public static function map(bool $activeOnly = true): array
    {
        try {
            $q = self::query();
            if ($activeOnly) {
                $q->where('is_active', true);
            }
            $rows = $q->orderBy('sort_order')->orderBy('id')->get(['key', 'label']);
            if ($rows->isEmpty()) {
                return self::DEFAULTS;
            }

            return $rows->pluck('label', 'key')->toArray();
        } catch (\Throwable) {
            return self::DEFAULTS;
        }
    }

    /**
     * 유형별 진행 단계 flow. DB에 저장된 flow가 있으면 그것을, 없으면 기본 flow를 반환.
     *
     * @return array<int, array{code: string, label: string, kind: string}>
     */
    public static function stagesFor(?string $key): array
    {
        $key = $key ?: 'visit';
        try {
            $stages = self::where('key', $key)->value('stages');
            if (! empty($stages)) {
                return is_array($stages) ? $stages : json_decode($stages, true);
            }
        } catch (\Throwable) {
            // 마이그레이션 이전 등 — 기본 flow로 폴백
        }

        return self::DEFAULT_FLOWS[$key] ?? self::DEFAULT_FLOWS['visit'];
    }
}

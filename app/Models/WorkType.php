<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkType extends Model
{
    protected $fillable = ['key', 'type_key', 'label', 'scale_keys', 'sort_order', 'is_active'];

    protected $casts = [
        'scale_keys' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * 관리자 정의(work_types)에서 지워진 키의 폴백 라벨 — 통계 등에서 원문 키 노출 방지.
     * 시드(2026_07_16 add_type_key_to_work_types_and_seed) 라벨 기준.
     *
     * @var array<string, string>
     */
    public const FALLBACK_LABELS = self::DEFAULTS + [
        'simple' => '단순',
        'general' => '일반',
        'revisit' => '재방문',
        'rush' => '급행',
        'outdoor_setup' => '야외방송 세팅(1차)',
        'edu_consulting' => '교육·컨설팅',
        'relay' => '중계',
        'outdoor' => '야외',
        'video' => '영상',
        'audio' => '음향',
        'rental' => '렌탈',
        'equip_single' => '장비(단품)',
        'daily' => '일대여',
        'shop' => '쇼핑몰',
    ];

    /** @var array<string, string> 기본 셋 (마이그레이션과 일치) */
    public const DEFAULTS = [
        'setup' => '세팅',
        'remote' => '원격',
        'survey' => '답사',
        'filming' => '촬영중계',
        'design' => '디자인',
        'as' => 'A/S',
        'troubleshoot' => '문제해결',
        'dispatch' => '파견',
        'monthly' => '월 계약',
        'hourly' => '시간 대여',
    ];

    /**
     * key=>label 매핑 (활성만). DB 비어있으면 DEFAULTS.
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
}

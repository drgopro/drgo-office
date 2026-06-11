<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkType extends Model
{
    protected $fillable = ['key', 'label', 'scale_keys', 'sort_order', 'is_active'];

    protected $casts = [
        'scale_keys' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @var array<string, string> 기본 셋 (마이그레이션과 일치) */
    public const DEFAULTS = [
        'setup' => '세팅',
        'remote' => '원격',
        'survey' => '답사',
        'filming' => '촬영중계',
        'design' => '디자인',
        'as' => 'A/S',
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

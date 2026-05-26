<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationType extends Model
{
    protected $fillable = ['key', 'label', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @var array<string, string> 기본 셋 (마이그레이션/시드에 사용) */
    public const DEFAULTS = [
        'visit' => '방문세팅',
        'remote' => '원격세팅',
        'design' => '디자인',
        'inquiry' => '단순문의',
        'as' => 'A/S',
        'troubleshoot' => '문제 해결',
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
}

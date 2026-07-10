<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CalendarCategory extends Model
{
    protected $fillable = ['key', 'label', 'color', 'text_color', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @var array<string, array{label:string,color:string,text_color:string}> */
    public const DEFAULTS = [
        'gold' => ['label' => '방문의뢰', 'color' => '#c8a870', 'text_color' => '#1a1207'],
        'teal' => ['label' => '원격/방송룸', 'color' => '#e8894a', 'text_color' => '#1a0a00'],
        'blue' => ['label' => '사내업무', 'color' => '#7aaec8', 'text_color' => '#061825'],
        'red' => ['label' => '휴가/개인', 'color' => '#c87070', 'text_color' => '#200808'],
        'green' => ['label' => '촬영/스튜디오', 'color' => '#70c870', 'text_color' => '#08200a'],
        'purple' => ['label' => '미팅/내방', 'color' => '#9b70c8', 'text_color' => '#f0eaff'],
    ];

    /**
     * 전체 카테고리를 key 기준 배열로 반환. DB가 비어있으면 기본값 사용.
     *
     * @return array<string, array{label:string,color:string,text_color:string}>
     */
    /** 요청 내 캐시 — 캘린더 렌더 시 map()이 10여 회 호출되므로 쿼리 1회로 제한 */
    protected static ?array $mapCache = null;

    public static function map(): array
    {
        if (static::$mapCache !== null) {
            return static::$mapCache;
        }

        return static::$mapCache = static::buildMap();
    }

    /**
     * 라벨로 카테고리 키 조회, 없으면 커스텀 카테고리 생성 후 키 반환.
     * (iCal 가져오기·렌탈/방송룸 계약 캘린더 동기화 공용)
     */
    public static function ensureByLabel(string $label): string
    {
        foreach (static::map() as $key => $c) {
            if (($c['label'] ?? '') === $label) {
                return $key;
            }
        }

        $known = ['디자인' => 'design', '렌탈' => 'rental', '촬영' => 'shoot', '스튜디오' => 'studio', '방송룸 대여' => 'broadcast_rental'];
        $base = $known[$label] ?? (preg_replace('/[^a-z0-9_]/', '', Str::slug($label, '_')) ?: 'cat_'.time());
        $key = $base;
        $i = 1;
        while (static::where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }
        static::create([
            'key' => $key,
            'label' => $label,
            'color' => '#8a9bb0',
            'text_color' => '#101820',
            'sort_order' => (static::max('sort_order') ?? 0) + 1,
            'is_active' => true,
        ]);
        static::$mapCache = null; // 캐시 무효화 — 같은 요청 내 후속 조회 반영

        return $key;
    }

    protected static function buildMap(): array
    {
        try {
            $rows = self::orderBy('sort_order')->get(['key', 'label', 'color', 'text_color']);
            if ($rows->isEmpty()) {
                return self::DEFAULTS;
            }

            return $rows->keyBy('key')->map(fn ($r) => [
                'label' => $r->label,
                'color' => $r->color,
                'text_color' => $r->text_color,
            ])->toArray();
        } catch (\Throwable) {
            return self::DEFAULTS;
        }
    }
}

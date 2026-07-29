<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class RequestItemPreset extends Model
{
    protected $fillable = ['title', 'children', 'sort_order', 'is_active'];

    /** 레거시 map 형태를 정렬할 때 쓰는 분류 표시 순서 (미지정 분류는 뒤에 원래 순서 유지) */
    public const CATEGORY_ORDER = ['기본 서비스', '의뢰 서비스', '컴퓨터', '카메라/조명', '오디오', '기타'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * children은 순서 보존을 위해 [{name, items[]}] 배열로 저장.
     * (map 형태는 MySQL JSON 타입이 객체 키를 재정렬해 분류 순서가 뒤섞임)
     */
    protected function children(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => self::normalizeChildren(json_decode($value ?? '[]', true)),
            set: fn ($value) => json_encode(self::normalizeChildren($value), JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * map(분류 => 항목[]) 또는 [{name, items}] → [{name, items}] 배열로 정규화.
     * 레거시 map은 CATEGORY_ORDER 순으로 정렬해 변환한다.
     *
     * @return array<int, array{name: string, items: array<int, string>}>
     */
    public static function normalizeChildren(mixed $children): array
    {
        if (! is_array($children)) {
            return [];
        }

        if (array_is_list($children)) {
            return collect($children)
                ->filter(fn ($c) => is_array($c) && isset($c['name']) && $c['name'] !== '')
                ->map(fn ($c) => [
                    'name' => (string) $c['name'],
                    'items' => array_values(array_map('strval', (array) ($c['items'] ?? []))),
                ])
                ->values()
                ->all();
        }

        $order = array_flip(self::CATEGORY_ORDER);

        return collect($children)
            ->map(fn ($items, $name) => [
                'name' => (string) $name,
                'items' => array_values(array_map('strval', (array) $items)),
            ])
            ->values()
            ->sortBy(fn ($c) => $order[$c['name']] ?? 999) // 안정 정렬 — 미지정 분류는 기존 순서 유지
            ->values()
            ->all();
    }
}

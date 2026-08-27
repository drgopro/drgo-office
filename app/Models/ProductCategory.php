<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use LogsActivity;

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'depth',
        'sort_order',
        'is_service',
    ];

    protected $casts = [
        'depth' => 'integer',
        'sort_order' => 'integer',
        'is_service' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * 루트부터 자신까지의 코드를 이어붙인 SKU 접두사 반환
     * 예: PCC-GAM
     */
    public function getSkuPrefix(): string
    {
        $codes = [$this->code];
        $node = $this;

        while ($node->parent_id) {
            $node = $node->parent;
            array_unshift($codes, $node->code);
        }

        return implode('-', $codes);
    }

    /**
     * SKU 자동 생성용 베이스 코드 — 정책: '2차 카테고리의 코드값'
     * - 2차 카테고리: 자기 코드
     * - 3·4차 카테고리: 2차 조상의 코드
     * - 1차만 있는 경우: 1차 코드 (폴백)
     */
    public function getSkuBaseCode(): string
    {
        $node = $this;
        // 2차 조상 찾기
        while ($node->depth > 2 && $node->parent_id) {
            $node = $node->parent;
        }

        return $node->code;
    }

    /**
     * 자신을 포함한 모든 후손 카테고리 ID
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /**
     * 후손 트리에서 최대 depth 반환 (자신 포함)
     */
    public function maxDepthInSubtree(): int
    {
        $max = $this->depth;
        foreach ($this->children as $child) {
            $childMax = $child->maxDepthInSubtree();
            if ($childMax > $max) {
                $max = $childMax;
            }
        }

        return $max;
    }

    /**
     * 자신과 모든 후손의 depth를 새 부모 기준으로 재계산
     */
    public function recalculateDepth(int $newDepth): void
    {
        $this->depth = $newDepth;
        $this->save();
        foreach ($this->children()->get() as $child) {
            $child->recalculateDepth($newDepth + 1);
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'depth',
        'sort_order',
    ];

    protected $casts = [
        'depth' => 'integer',
        'sort_order' => 'integer',
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
}

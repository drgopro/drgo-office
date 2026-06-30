<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WikiCategory extends Model
{
    protected $fillable = ['parent_id', 'name', 'sort_order'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function wikis()
    {
        return $this->hasMany(Wiki::class, 'category_id');
    }

    /** 루트로부터의 깊이 (최상위=1) */
    public function depth(): int
    {
        $depth = 1;
        $node = $this;
        while ($node->parent_id) {
            $depth++;
            $node = $node->parent;
        }

        return $depth;
    }
}

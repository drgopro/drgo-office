<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestItemPreset extends Model
{
    protected $fillable = ['title', 'children', 'sort_order', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'children' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

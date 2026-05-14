<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientFieldDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'label', 'type', 'section', 'width', 'has_quantity', 'options',
        'placeholder', 'help_text', 'is_required', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'has_quantity' => 'boolean',
        'sort_order' => 'integer',
        'width' => 'integer',
    ];

    public const TYPES = ['text', 'textarea', 'select', 'radio', 'checkbox', 'number', 'date'];

    public const SECTIONS = [
        'basic' => '기본 정보',
        'equipment' => '장비 정보',
        'broadcast' => '방송 정보',
        'business' => '사업자 정보',
        'etc' => '기타',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('section')->orderBy('sort_order')->orderBy('id');
    }
}

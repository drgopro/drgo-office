<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectFieldDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'label', 'type', 'section', 'subsection', 'width', 'has_quantity', 'options',
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
        'schedule' => '일정 정보',
        'billing' => '금액/결제',
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

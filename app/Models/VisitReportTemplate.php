<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitReportTemplate extends Model
{
    protected $fillable = [
        'name',
        'content',
        'sort_order',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoProject extends Model
{
    protected $fillable = [
        'client_id', 'client_name', 'requester_type', 'project_type', 'work_type',
        'tags', 'free_name', 'stage', 'status', 'cancel_reason', 'billing', 'stage_data', 'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'billing' => 'array',
        'stage_data' => 'array',
    ];
}

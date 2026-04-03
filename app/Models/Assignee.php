<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_order',
        'is_active',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'schedule_assignees');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

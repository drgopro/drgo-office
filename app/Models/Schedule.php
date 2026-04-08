<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_all_day',
        'color',
        'client_name',
        'address',
        'location',
        'description',
        'notif_minutes',
        'is_locked',
        'is_private',
        'special_opts',
        'sched_opt',
        'sched_event_opts',
        'sched_after_days',
        'sched_after_date',
        'sched_after_reason',
        'gold_data',
        'teal_data',
        'created_by',
    ];

    protected $casts = [
        'is_all_day' => 'boolean',
        'is_locked' => 'boolean',
        'is_private' => 'boolean',
        'special_opts' => 'array',
        'sched_event_opts' => 'array',
        'gold_data' => 'array',
        'teal_data' => 'array',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'sched_after_date' => 'date:Y-m-d',
    ];

    public function assignees()
    {
        return $this->belongsToMany(Assignee::class, 'schedule_assignees');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(ScheduleLog::class);
    }

    public function changes()
    {
        return $this->hasMany(ScheduleChange::class)->orderByDesc('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(ScheduleAttachment::class)->orderBy('sort_order');
    }
}

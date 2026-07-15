<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_all_day',
        'exclude_weekends',
        'ship_icon_override',
        'color',
        'client_name',
        'address',
        'location',
        'description',
        'special_note',
        'handover_note',
        'notif_minutes',
        'notified_at',
        'repeat_group',
        'repeat_freq',
        'repeat_interval',
        'repeat_unit',
        'repeat_until',
        'import_uid',
        'source_type',
        'source_id',
        'notify_assignees',
        'is_locked',
        'is_private',
        'special_opts',
        'sched_opt',
        'sched_event_opts',
        'sched_after_days',
        'sched_after_date',
        'sched_after_reason',
        'request_data',
        'remote_data',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'is_all_day' => 'boolean',
        'exclude_weekends' => 'boolean',
        'is_locked' => 'boolean',
        'is_private' => 'boolean',
        'special_opts' => 'array',
        'notify_assignees' => 'array',
        'sched_event_opts' => 'array',
        'request_data' => 'array',
        'remote_data' => 'array',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'sched_after_date' => 'date:Y-m-d',
        'repeat_until' => 'date:Y-m-d',
        'repeat_interval' => 'integer',
        'completed_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function assignees()
    {
        return $this->belongsToMany(Assignee::class, 'schedule_assignees');
    }

    /**
     * 알림 수신자 — 알림 대상 지정(notify_assignees)이 있으면 그 멤버, 없으면 담당자 전체, 둘 다 없으면 등록자.
     *
     * @return Collection<int, User>
     */
    public function notificationRecipients()
    {
        $notifyIds = collect($this->notify_assignees ?? []);
        $userIds = $notifyIds->isNotEmpty()
            ? Assignee::whereIn('id', $notifyIds)->pluck('user_id')->filter()->unique()
            : $this->assignees()->pluck('user_id')->filter()->unique();

        if ($userIds->isEmpty() && $this->created_by) {
            $userIds = collect([$this->created_by]);
        }

        return User::whereIn('id', $userIds)->where('is_active', true)->get();
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

    public function shipments()
    {
        return $this->hasMany(ScheduleShipment::class);
    }
}

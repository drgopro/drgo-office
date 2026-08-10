<?php

namespace App\Models;

use App\Services\ChannelTalkNotifier;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'parent_id',
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

    /** 장기 일정의 하위 일정 (일자별 시간·담당자) — 날짜·시간순 */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('start_date')->orderBy('start_time');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** 상위 일정만 (하위 제외) — 집계·목록용 */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /** 하위 담당자 합집합을 부모 담당자에 반영 (기존 순서 유지, 새 인원은 뒤에 추가) */
    public function syncAssigneesFromChildren(): void
    {
        $current = $this->assignees->pluck('id')->all();
        $childIds = self::where('parent_id', $this->id)
            ->with('assignees')->get()
            ->flatMap(fn ($c) => $c->assignees->pluck('id'))->unique()->all();
        $merged = array_values(array_unique([...$current, ...$childIds]));
        if ($merged !== $current) {
            // 하위 일정 담당 지정 시 이미 알림이 갔으므로 부모 합산은 중복 알림 방지
            $this->syncAssigneesOrdered($merged, notify: false);
        }
    }

    public function assignees()
    {
        // 선택 순서대로 표시 (sort_order = 모달에서 클릭한 순서)
        return $this->belongsToMany(Assignee::class, 'schedule_assignees')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * 담당자 동기화 — 배열 순서를 pivot sort_order로 저장해 선택 순서 보존.
     *
     * @param  array<int, int|string>  $ids
     */
    public function syncAssigneesOrdered(array $ids, bool $notify = true): void
    {
        $changes = $this->assignees()->sync(
            collect($ids)->values()->mapWithKeys(fn ($id, $i) => [(int) $id => ['sort_order' => $i]])->all()
        );

        // 담당자 추가/제거 채널톡 알림 (백업 가져오기 등 대량 작업은 notify: false)
        if ($notify && ($changes['attached'] || $changes['detached'])) {
            app(ChannelTalkNotifier::class)
                ->scheduleAssigneesChanged($this, $changes['attached'], $changes['detached']);
        }
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

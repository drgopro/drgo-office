<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    protected $fillable = [
        'username',
        'display_name',
        'email',
        'password',
        'role',
        'team_id',
        'hire_date',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'hire_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // 사용자 생성/수정 시 캘린더 담당자(Assignee) 동기화 — 매 조회마다 하던 것을 저장 시점으로 이동
        static::saved(function (User $user) {
            $user->syncAssignee();
        });
    }

    /**
     * 캘린더 담당자 풀과 동기화.
     * master/admin/member 활성 사용자는 Assignee 활성, 그 외(비활성/guest)는 비활성 처리.
     */
    public function syncAssignee(): void
    {
        $eligible = $this->is_active && in_array($this->role, ['master', 'admin', 'member'], true);

        $assignee = Assignee::firstWhere('user_id', $this->id);

        if (! $eligible) {
            $assignee?->update(['is_active' => false]);

            return;
        }

        if ($assignee) {
            $assignee->update(['is_active' => true]);
        } else {
            Assignee::create([
                'user_id' => $this->id,
                'name' => $this->display_name ?? $this->username,
                'is_active' => true,
                'display_order' => 0,
            ]);
        }
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['master', 'admin']);
    }

    public function isGuest(): bool
    {
        return $this->role === 'guest';
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isGuest()) {
            return $key === 'calendar.view';
        }

        return $this->team && in_array($key, $this->team->permissions ?? []);
    }

    /**
     * 연차 관리 접근 — 인사 정보라 admin도 자동 통과하지 않는다.
     * master 또는 팀 권한(leave.manage)이 있는 팀원만 가능.
     */
    public function canManageLeave(): bool
    {
        return $this->role === 'master'
            || ($this->team && in_array('leave.manage', $this->team->permissions ?? []));
    }
}

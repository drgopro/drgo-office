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
        'fiscal_leave',
        'is_active',
        'resigned_at',
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
            'fiscal_leave' => 'boolean',
            'is_active' => 'boolean',
            'resigned_at' => 'datetime',
        ];
    }

    /** 퇴사 처리 여부 — 퇴사자는 로그인·접근이 차단되고 담당자 목록에서 제외된다 (기록은 보존) */
    public function isResigned(): bool
    {
        return $this->resigned_at !== null;
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
        $eligible = $this->is_active && ! $this->isResigned() && in_array($this->role, ['master', 'admin', 'member'], true);

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

    /**
     * 피드백 AI 검수 열람·승인 권한 — 마스터는 항상, 그 외에는 마스터가 지정한 사용자(설정 feedback_review_viewers)만.
     * 관리자(admin) 역할이라는 이유만으로는 볼 수 없다.
     */
    public function canSeeFeedbackReviews(): bool
    {
        if ($this->role === 'master') {
            return true;
        }

        return in_array($this->id, self::feedbackReviewViewerIds(), true);
    }

    /** @return array<int, int> 마스터가 지정한 AI 검수 열람자 ID 목록 */
    public static function feedbackReviewViewerIds(): array
    {
        $ids = json_decode((string) Setting::get('feedback_review_viewers', '[]'), true);

        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
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

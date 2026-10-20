<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** 공지사항 — 위키에서 분리한 독립 게시판 (작성/수정/삭제는 관리자 전용) */
class Notice extends Model
{
    use LogsActivity;

    protected $fillable = [
        'title',
        'category_id',
        'content',
        'is_pinned',
        'pinned_until',
        'is_draft',
        'allowed_team_ids',
        'sort_order',
        'legacy_wiki_id', // 위키에서 이관된 공지의 원본 id — /wiki/{id} 리다이렉트용
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_draft' => 'boolean',
        'pinned_until' => 'date',
        'allowed_team_ids' => 'array',
    ];

    /** 발행된 글만 (임시저장 제외) */
    public function scopePublished($query)
    {
        return $query->where('is_draft', false);
    }

    /** 열람 권한 필터 — 관리자는 전체, 그 외에는 전체 공개 + 본인 작성 + 소속 팀 허용 문서만 */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->whereNull('allowed_team_ids')->orWhere('created_by', $user->id);
            if ($user->team_id) {
                $q->orWhereJsonContains('allowed_team_ids', (int) $user->team_id);
            }
        });
    }

    /** 현재 상단 고정 유효 여부 — 고정 기한(pinned_until)이 지나면 일반 글처럼 정렬 */
    public function isPinnedNow(): bool
    {
        return $this->is_pinned && (! $this->pinned_until || $this->pinned_until->endOfDay()->isFuture());
    }

    public function canView(User $user): bool
    {
        if ($user->isAdmin() || $this->created_by === $user->id || empty($this->allowed_team_ids)) {
            return true;
        }

        return $user->team_id && in_array((int) $user->team_id, array_map('intval', $this->allowed_team_ids), true);
    }

    /** 공지 작성/수정/삭제는 관리자 전용 */
    public function canEdit(User $user): bool
    {
        return $user->isAdmin();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments()
    {
        return $this->hasMany(NoticeAttachment::class)->orderByDesc('created_at');
    }

    public function categoryNode()
    {
        return $this->belongsTo(NoticeCategory::class, 'category_id');
    }

    /** 읽음 확인 기록 — 문서를 연 사용자 (문서당 사용자 1행) */
    public function reads()
    {
        return $this->hasMany(NoticeRead::class);
    }
}

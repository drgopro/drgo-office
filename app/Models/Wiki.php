<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Wiki extends Model
{
    use LogsActivity;

    /** 특수 유형(카테고리와 별개로 고정 섹션 관리) — 코드 → 라벨 */
    public const SPECIAL_TYPES = ['notice' => '공지사항', 'update' => '업데이트', 'meeting' => '회의록'];

    /** 특수 유형 중 관리자만 작성/수정/삭제할 수 있는 유형 (회의록은 전 직원 가능) */
    public const ADMIN_ONLY_TYPES = ['notice', 'update'];

    protected $fillable = [
        'title',
        'category',
        'category_id',
        'type',
        'content',
        'diagram_data',
        'is_pinned',
        'is_draft',
        'allowed_team_ids',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_draft' => 'boolean',
        'allowed_team_ids' => 'array',
        'diagram_data' => 'array',
    ];

    /** 발행된 글만 (임시저장 제외) — 목록/검색/대시보드 공용 */
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

    /** 이 문서를 열람할 수 있는가 — 전체 공개 / 작성자 / 관리자 / 허용 팀 소속 */
    public function canView(User $user): bool
    {
        if ($user->isAdmin() || $this->created_by === $user->id || empty($this->allowed_team_ids)) {
            return true;
        }

        return $user->team_id && in_array((int) $user->team_id, array_map('intval', $this->allowed_team_ids), true);
    }

    /** 이 문서를 수정/삭제할 수 있는가 — 게스트 불가, 공지/업데이트는 관리자만 */
    public function canEdit(User $user): bool
    {
        if ($user->isGuest()) {
            return false;
        }
        if (in_array($this->type, self::ADMIN_ONLY_TYPES, true)) {
            return $user->isAdmin();
        }

        return true;
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
        return $this->hasMany(WikiAttachment::class)->orderByDesc('created_at');
    }

    public function categoryNode()
    {
        return $this->belongsTo(WikiCategory::class, 'category_id');
    }

    /** 댓글 — 회의록(meeting) 유형 게시물 전용 */
    public function comments()
    {
        return $this->hasMany(WikiComment::class)->orderBy('created_at');
    }

    /**
     * 목록으로 돌아갈 때 이 문서의 분류가 선택되도록 하는 쿼리 파라미터.
     *
     * @return array{type?: string, cat?: int|string}
     */
    public function listFilterParams(): array
    {
        if ($this->type !== 'normal') {
            return ['type' => $this->type];
        }

        return ['cat' => $this->category_id ?: 'uncat'];
    }
}

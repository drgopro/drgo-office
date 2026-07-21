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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_draft' => 'boolean',
        'diagram_data' => 'array',
    ];

    /** 발행된 글만 (임시저장 제외) — 목록/검색/대시보드 공용 */
    public function scopePublished($query)
    {
        return $query->where('is_draft', false);
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
}

<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Wiki extends Model
{
    use LogsActivity;

    /** 특수 유형(카테고리와 별개로 고정 섹션 관리) — 코드 → 라벨 */
    public const SPECIAL_TYPES = ['notice' => '공지사항', 'update' => '업데이트'];

    protected $fillable = [
        'title',
        'category',
        'category_id',
        'type',
        'content',
        'diagram_data',
        'is_pinned',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'diagram_data' => 'array',
    ];

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
}

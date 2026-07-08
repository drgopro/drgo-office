<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackPost extends Model
{
    use SoftDeletes;

    /** 상태 → 한글 라벨 (검토중 = 수락된 단계) */
    public const STATUS_LABELS = [
        'waiting' => '대기',
        'reviewing' => '검토중',
        'hold' => '보류',
        'done' => '완료',
        'rejected' => '반려',
    ];

    protected $fillable = [
        'type',
        'title',
        'body',
        'page',
        'status',
        'reject_reason',
        'created_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<FeedbackComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class)->orderBy('created_at');
    }

    /** @return HasMany<FeedbackAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(FeedbackAttachment::class);
    }

    /** 완료·반려된 글은 작성자 수정 잠금 */
    public function isLockedForAuthor(): bool
    {
        return in_array($this->status, ['done', 'rejected'], true);
    }
}

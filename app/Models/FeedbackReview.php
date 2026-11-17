<?php

namespace App\Models;

use Database\Factories\FeedbackReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 피드백 AI 검수 — 글당 1건. 관리자(master/admin)에게만 노출되고, 관리자가 오피스에서 승인해야 수정이 진행된다.
 */
class FeedbackReview extends Model
{
    /** @use HasFactory<FeedbackReviewFactory> */
    use HasFactory;

    /** 분류 → 한글 라벨 */
    public const CATEGORY_LABELS = [
        'bug' => '버그',
        'improvement' => '개선 요청',
        'already_done' => '이미 해결됨',
        'duplicate' => '중복',
        'need_info' => '정보 부족',
        'other' => '기타',
    ];

    /** 작업 크기 → 한글 라벨 */
    public const EFFORT_LABELS = [
        'small' => '작음',
        'medium' => '보통',
        'large' => '큼',
    ];

    /** 관리자 결정 → 한글 라벨 (revise = 메모를 반영해 다시 검수) */
    public const DECISION_LABELS = [
        'pending' => '승인 대기',
        'approved' => '승인',
        'hold' => '보류',
        'rejected' => '반려',
        'revise' => '재검토 요청',
    ];

    protected $fillable = [
        'feedback_post_id',
        'category',
        'effort',
        'summary',
        'proposal',
        'reviewed_at',
        'decision',
        'decision_note',
        'decided_by',
        'decided_at',
        'result_note',
        'applied_at',
    ];

    protected $attributes = [
        'decision' => 'pending',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    /** @return BelongsTo<FeedbackPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedbackPost::class, 'feedback_post_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'category' => $this->category,
            'category_label' => self::CATEGORY_LABELS[$this->category] ?? $this->category,
            'effort' => $this->effort,
            'effort_label' => $this->effort ? (self::EFFORT_LABELS[$this->effort] ?? $this->effort) : null,
            'summary' => $this->summary,
            'proposal' => $this->proposal,
            'decision' => $this->decision,
            'decision_label' => self::DECISION_LABELS[$this->decision] ?? $this->decision,
            'decision_note' => $this->decision_note,
            'decided_by' => $this->decider?->display_name ?? $this->decider?->username,
            'decided_at' => $this->decided_at?->format('n/j H:i'),
            'result_note' => $this->result_note,
            'applied_at' => $this->applied_at?->format('n/j H:i'),
            'reviewed_at' => ($this->reviewed_at ?? $this->created_at)?->format('n/j H:i'),
        ];
    }
}

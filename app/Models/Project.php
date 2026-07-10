<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * 프로젝트 진행 단계(stage) 코드 → 한글 라벨 (단일 출처).
     *
     * @var array<string, string>
     */
    public const STAGE_LABELS = [
        'consulting' => '상담',
        'equipment' => '장비파악',
        'proposal' => '일정제안',
        'estimate' => '견적/계약',
        'payment' => '결제/예약',
        'visit' => '세팅',
        'as' => 'AS',
        'done' => '완료',
        'cancelled' => '취소',
    ];

    protected $fillable = [
        'client_id',
        'name',
        'project_type',
        'client_scale',
        'work_type',
        'tags',
        'stage',
        'status',
        'is_payment_only',
        'assigned_user_id',
        'overview',
        'visit_report',
        'as_deadline',
        'completed_at',
        'cancel_reason',
        'cancel_detail',
        'cancelled_at',
        'custom_data',
        'payment_info',
        'stage_data',
    ];

    protected $casts = [
        'as_deadline' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'custom_data' => 'array',
        'payment_info' => 'array',
        'stage_data' => 'array',
        'is_payment_only' => 'boolean',
        'tags' => 'array',
    ];

    /** stage 코드에 대응하는 한글 라벨 (미정의 코드는 코드 그대로 반환) */
    public function stageLabel(): string
    {
        return self::STAGE_LABELS[$this->stage] ?? (string) $this->stage;
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(ProjectFeedback::class)->orderByDesc('created_at');
    }

    // 하위 호환 — 기존 코드의 ->memos() 호출 지원
    public function memos()
    {
        return $this->feedbacks();
    }
}

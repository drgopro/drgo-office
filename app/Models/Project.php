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
     * 진행 단계(stage) 코드 → 한글 폴백 라벨.
     * 유형별 라벨은 유형 flow(consultation_types.stages JSON)가 우선 — stageLabel() 참고.
     *
     * @var array<string, string>
     */
    public const STAGE_LABELS = [
        'consulting' => '상담',
        'equipment' => '장비파악',
        'proposal' => '일정제안',
        'survey' => '사전답사',
        'estimate' => '견적/계약',
        'payment' => '결제/예약',
        'visit' => '세팅',
        'delivery' => '납품',
        'as' => 'AS',
        'done' => '완료',
        'cancelled' => '취소',
    ];

    protected $fillable = [
        'client_id',
        'manual_client_name',
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

    /**
     * 이 프로젝트 유형의 진행 단계 flow.
     *
     * @return array<int, array{code: string, label: string, kind: string}>
     */
    public function flowStages(): array
    {
        return ConsultationType::stagesFor($this->project_type);
    }

    /** 유형 flow 기준 단계 라벨 (flow에 없는 코드는 공통 폴백 라벨) */
    public function stageLabel(): string
    {
        foreach ($this->flowStages() as $s) {
            if (($s['code'] ?? null) === $this->stage) {
                return $s['label'] ?? $this->stage;
            }
        }

        return self::STAGE_LABELS[$this->stage] ?? (string) $this->stage;
    }

    /** 의뢰자 표시명 — 연동 의뢰자 우선, 미연동이면 주관식 이름(의뢰자명 확인 불가) */
    public function clientDisplayName(): ?string
    {
        if ($this->client) {
            return $this->client->name ?: $this->client->nickname;
        }

        return $this->manual_client_name;
    }

    public function payments()
    {
        return $this->hasMany(ProjectPayment::class)->orderByDesc('created_at');
    }

    /** 청구 (청구·잔금 관리) */
    public function billings()
    {
        return $this->hasMany(ProjectBilling::class)->orderByDesc('billed_at');
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

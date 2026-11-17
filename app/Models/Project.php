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
        'address',
        'address_detail',
        'visit_report',
        'visit_report_updated_at',
        'marketing_consent',
        'marketing_consent_updated_at',
        'marketing_posted_at',
        'as_deadline',
        'completed_at',
        'completed_by',
        'cancel_reason',
        'cancel_detail',
        'cancelled_at',
        'cancelled_from_stage',
        'custom_data',
        'payment_info',
        'stage_data',
    ];

    protected $casts = [
        'as_deadline' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'visit_report_updated_at' => 'datetime',
        'custom_data' => 'array',
        'payment_info' => 'array',
        'stage_data' => 'array',
        'is_payment_only' => 'boolean',
        'marketing_consent' => 'boolean',
        'marketing_consent_updated_at' => 'datetime',
        'marketing_posted_at' => 'datetime',
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

    /** 추가 담당자 — 대표 담당자(assigned_user_id) 외에 함께 맡는 사용자 */
    public function extraAssignees()
    {
        return $this->belongsToMany(User::class, 'project_assignees')->withTimestamps();
    }

    /** 완료 처리한 사용자 */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * 추가 담당자 저장 — 대표 담당자와 중복되는 사용자는 제외한다.
     *
     * @param  array<int, int|string>  $userIds
     */
    public function syncExtraAssignees(array $userIds): void
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $this->assigned_user_id)
            ->unique()->values()->all();
        $this->extraAssignees()->sync($ids);
    }

    /**
     * 현재 담당자 전원의 사용자 id (대표 + 추가, 중복 없음).
     *
     * @return array<int, int>
     */
    public function assigneeUserIds(): array
    {
        return collect([$this->assigned_user_id])
            ->merge($this->extraAssignees()->pluck('users.id'))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * 담당자 이름 목록 — 대표 담당자 먼저, 이어서 추가 담당자.
     *
     * @return array<int, string>
     */
    public function assigneeNames(): array
    {
        return collect([$this->assignedUser])->merge($this->extraAssignees)
            ->filter()->unique('id')
            ->map(fn (User $u) => $u->display_name ?: $u->username)->values()->all();
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

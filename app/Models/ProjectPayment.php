<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProjectPayment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'project_id',
        'parent_payment_id',
        'billing_id',
        'type',
        'estimate_id',
        'amount',
        'items',
        'method',
        'paid_at',
        'memo',
        'recorded_by',
    ];

    protected $casts = [
        'items' => 'array',
        'paid_at' => 'date:Y-m-d',
        'amount' => 'integer',
    ];

    public const TYPES = ['charge', 'refund', 'cancel'];

    /**
     * 로그 표시: '10,000원 결제 / 5,000원 환불 / 3,000원 결제 취소'
     */
    protected function getActivityLabel(): string
    {
        $typeLabel = [
            'charge' => '결제',
            'refund' => '환불',
            'cancel' => '결제 취소',
        ][$this->type] ?? $this->type;

        $amountAbs = abs((int) $this->amount);
        $amountStr = number_format($amountAbs);

        return "[프로젝트 결제] {$amountStr}원 {$typeLabel}";
    }

    protected function getActivitySummary(string $action): string
    {
        $typeLabel = [
            'charge' => '결제',
            'refund' => '환불',
            'cancel' => '결제 취소',
        ][$this->type] ?? $this->type;

        $amountStr = number_format(abs((int) $this->amount));

        return match ($action) {
            'create' => "{$amountStr}원 {$typeLabel} 처리",
            'delete' => "{$amountStr}원 {$typeLabel} 기록 삭제",
            'update' => "{$amountStr}원 {$typeLabel} 기록 수정",
            default => parent::getActivitySummary($action),
        };
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_payment_id');
    }

    /** 연결된 청구 (청구·잔금 관리) */
    public function billing()
    {
        return $this->belongsTo(ProjectBilling::class, 'billing_id');
    }

    public function refunds()
    {
        return $this->hasMany(self::class, 'parent_payment_id')
            ->whereIn('type', ['refund', 'cancel']);
    }

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

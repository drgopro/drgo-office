<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBilling extends Model
{
    use LogsActivity;

    public const STATUSES = ['unpaid', 'partial', 'paid'];

    protected $fillable = [
        'project_id',
        'amount',
        'billed_at',
        'status',
        'memo',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'billed_at' => 'date:Y-m-d',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** 이 청구에 연결된 입금(charge)·환불 트랜잭션 */
    /** @return HasMany<ProjectPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ProjectPayment::class, 'billing_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 입금 합계 (환불/취소는 음수로 반영) */
    public function paidTotal(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    /** 잔금 = 청구액 − 입금 합계 (0 미만이면 0) */
    public function balance(): int
    {
        return max(0, $this->amount - $this->paidTotal());
    }

    /** 입금 합계 기준으로 status 재계산 후 저장 */
    public function refreshStatus(): void
    {
        $paid = $this->paidTotal();
        $this->update(['status' => $paid <= 0 ? 'unpaid' : ($paid >= $this->amount ? 'paid' : 'partial')]);
    }
}

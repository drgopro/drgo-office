<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 렌탈 결제 체크 — 계약의 결제일 하나가 결제됐음을 기록 (행 존재 = 결제 완료) */
class RentalPayment extends Model
{
    protected $fillable = ['contract_id', 'due_date', 'amount', 'paid_at', 'paid_by', 'memo'];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}

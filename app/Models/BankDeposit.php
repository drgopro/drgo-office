<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankDeposit extends Model
{
    protected $fillable = [
        'received_at',
        'amount',
        'depositor_name',
        'bank',
        'balance_after',
        'raw_text',
        'source',
        'dedup_hash',
        'estimate_id',
        'matched_at',
        'matched_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'amount' => 'integer',
        'balance_after' => 'integer',
        'matched_at' => 'datetime',
    ];

    /** 매칭된 견적서 — 직원이 수동으로 연결 (계좌이체 결제완료 처리) */
    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function matcher()
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}

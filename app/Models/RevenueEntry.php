<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 매출 인식 원장 — 통계 전용 파생 테이블. 원본은 견적서/결제 원장이며
 * revenue:rebuild로 언제든 전체 재계산할 수 있다 (직접 수정하지 않는다).
 */
class RevenueEntry extends Model
{
    protected $fillable = [
        'kind',
        'estimate_id',
        'project_id',
        'payment_id',
        'recognized_on',
        'product_amount',
        'service_amount',
        'amount',
    ];

    protected $casts = [
        'recognized_on' => 'date',
        'product_amount' => 'integer',
        'service_amount' => 'integer',
        'amount' => 'integer',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 페이앱 자체(외부) 결제 — 견적서와 무관하게 feedback 웹훅으로 수집된 결제 */
class PayappPayment extends Model
{
    protected $fillable = [
        'mul_no',
        'pay_state',
        'price',
        'goodname',
        'buyer',
        'recvphone',
        'pay_type',
        'card_name',
        'csturl',
        'paid_at',
        'requested_at',
    ];

    protected $casts = [
        'pay_state' => 'integer',
        'price' => 'integer',
        'paid_at' => 'datetime',
        'requested_at' => 'datetime',
    ];
}

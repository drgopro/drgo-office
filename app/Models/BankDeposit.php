<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankDeposit extends Model
{
    protected $fillable = [
        'received_at',
        'amount',
        'depositor_name',
        'balance_after',
        'raw_text',
        'source',
        'dedup_hash',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'amount' => 'integer',
        'balance_after' => 'integer',
    ];
}

<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use LogsActivity;

    protected $fillable = [
        'status',
        'supplier',
        'items',
        'total_amount',
        'requested_by',
        'approved_by',
        'expected_date',
        'received_date',
        'memo',
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'decimal:0',
        'expected_date' => 'date',
        'received_date' => 'date',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

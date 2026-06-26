<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoPayment extends Model
{
    protected $fillable = [
        'demo_project_id', 'parent_payment_id', 'type',
        'amount', 'items', 'method', 'paid_at', 'memo', 'recorded_by',
    ];

    protected $casts = [
        'items' => 'array',
        'paid_at' => 'date:Y-m-d',
        'amount' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(DemoProject::class, 'demo_project_id');
    }

    public function refunds()
    {
        return $this->hasMany(self::class, 'parent_payment_id')
            ->whereIn('type', ['refund', 'cancel']);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectPayment extends Model
{
    protected $fillable = [
        'project_id',
        'parent_payment_id',
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

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_payment_id');
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

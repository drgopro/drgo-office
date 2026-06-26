<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoProject extends Model
{
    protected $fillable = [
        'client_id', 'client_name', 'client_phone', 'client_address',
        'requester_type', 'project_type', 'work_type',
        'tags', 'free_name', 'overview', 'stage', 'status', 'cancel_reason',
        'billing', 'stage_data', 'created_by',
        'has_balance', 'balance_amount',
    ];

    protected $casts = [
        'tags' => 'array',
        'billing' => 'array',
        'stage_data' => 'array',
        'has_balance' => 'boolean',
        'balance_amount' => 'integer',
    ];

    public function payments()
    {
        return $this->hasMany(DemoPayment::class, 'demo_project_id');
    }

    public function consultations()
    {
        return $this->hasMany(DemoConsultation::class)->orderByDesc('consulted_at')->orderByDesc('id');
    }

    public function feedbacks()
    {
        return $this->hasMany(DemoFeedback::class)->orderByDesc('id');
    }
}

<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    use LogsActivity;

    protected $fillable = [
        'client_id',
        'project_id',
        'client_name',
        'client_nickname',
        'client_phone',
        'product_items',
        'service_items',
        'product_total',
        'service_total',
        'total_amount',
        'status',
        'validity_days',
        'issued_at',
        'memo',
        'created_by',
    ];

    protected $casts = [
        'product_items' => 'array',
        'service_items' => 'array',
        'product_total' => 'integer',
        'service_total' => 'integer',
        'total_amount' => 'integer',
        'validity_days' => 'integer',
        'issued_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

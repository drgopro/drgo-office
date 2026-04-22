<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalContract extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id', 'start_date', 'end_date', 'monthly_fee', 'status', 'memo',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'monthly_fee' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

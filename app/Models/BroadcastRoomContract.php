<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastRoomContract extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id', 'start_date', 'end_date', 'monthly_fee', 'status', 'memo', 'calendar_meta',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'monthly_fee' => 'integer',
        'calendar_meta' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

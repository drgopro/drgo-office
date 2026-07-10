<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastRoomUsage extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id', 'room_no', 'used_date', 'start_at', 'end_at', 'schedule_id', 'hours', 'fee', 'memo',
    ];

    protected $casts = [
        'used_date' => 'date:Y-m-d',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'hours' => 'decimal:2',
        'fee' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}

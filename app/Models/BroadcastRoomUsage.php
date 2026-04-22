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
        'client_id', 'used_date', 'hours', 'fee', 'memo',
    ];

    protected $casts = [
        'used_date' => 'date:Y-m-d',
        'hours' => 'decimal:2',
        'fee' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

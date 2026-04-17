<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'item_id',
        'target_id',
        'action',
        'detail',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function item()
    {
        return $this->belongsTo(RentalItem::class, 'item_id');
    }

    public function target()
    {
        return $this->belongsTo(RentalTarget::class, 'target_id');
    }
}

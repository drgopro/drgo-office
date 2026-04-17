<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalTarget extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'note',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(RentalItem::class, 'current_target_id');
    }

    public function logs()
    {
        return $this->hasMany(RentalLog::class, 'target_id');
    }
}

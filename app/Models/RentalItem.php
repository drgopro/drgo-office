<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'serial',
        'category',
        'components',
        'description',
        'current_target_id',
    ];

    public function currentTarget()
    {
        return $this->belongsTo(RentalTarget::class, 'current_target_id');
    }

    public function logs()
    {
        return $this->hasMany(RentalLog::class, 'item_id');
    }
}

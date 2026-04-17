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
        'category_id',
        'components',
        'description',
        'current_target_id',
        'home_target_id',
        'group_id',
    ];

    public function category()
    {
        return $this->belongsTo(RentalCategory::class, 'category_id');
    }

    public function currentTarget()
    {
        return $this->belongsTo(RentalTarget::class, 'current_target_id');
    }

    public function homeTarget()
    {
        return $this->belongsTo(RentalTarget::class, 'home_target_id');
    }

    public function group()
    {
        return $this->belongsTo(RentalGroup::class, 'group_id');
    }

    public function logs()
    {
        return $this->hasMany(RentalLog::class, 'item_id');
    }
}

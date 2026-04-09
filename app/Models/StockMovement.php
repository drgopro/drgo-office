<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use LogsActivity;

    protected $fillable = [
        'product_id',
        'movement_type',
        'quantity',
        'quantity_after',
        'user_id',
        'project_id',
        'memo',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_after' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}

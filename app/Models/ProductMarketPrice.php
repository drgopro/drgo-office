<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMarketPrice extends Model
{
    protected $fillable = [
        'product_id',
        'vendor',
        'url',
        'price',
        'checked_at',
        'error',
    ];

    protected $casts = [
        'price' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

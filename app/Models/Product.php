<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'category',
        'category_id',
        'purchase_price',
        'sale_price',
        'market_price_url',
        'market_price',
        'market_price_checked_at',
        'market_price_error',
        'safety_stock',
        'memo',
        'is_active',
        'show_in_estimate',
    ];

    protected $casts = [
        'purchase_price' => 'integer',
        'sale_price' => 'integer',
        'market_price' => 'integer',
        'market_price_checked_at' => 'datetime',
        'safety_stock' => 'integer',
        'is_active' => 'boolean',
        'show_in_estimate' => 'boolean',
    ];

    public function categoryRelation()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}

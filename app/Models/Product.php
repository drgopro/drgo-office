<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'category',
        'category_id',
        'purchase_price',
        'sale_price',
        'safety_stock',
        'memo',
        'is_active',
        'show_in_estimate',
    ];

    protected $casts = [
        'purchase_price' => 'integer',
        'sale_price' => 'integer',
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

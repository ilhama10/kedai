<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_id',
        'menu_name_snapshot',
        'unit_price_snapshot',
        'cost_amount_snapshot',
        'quantity',
        'total_price',
        'notes',
        'image_snapshot',
    ];

    protected $casts = [
        'unit_price_snapshot' => 'float',
        'cost_amount_snapshot' => 'float',
        'total_price' => 'float',
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function variants()
    {
        return $this->hasMany(OrderItemVariant::class);
    }

    public function addons()
    {
        return $this->hasMany(OrderItemAddon::class);
    }

    public function costs()
    {
        return $this->hasMany(OrderItemCost::class);
    }
}

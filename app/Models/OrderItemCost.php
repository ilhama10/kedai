<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemCost extends Model
{
    protected $fillable = [
        'order_item_id',
        'inventory_id',
        'inventory_name_snapshot',
        'quantity_consumed',
        'unit_snapshot',
        'cost_per_unit_snapshot',
        'cost_amount',
    ];

    protected $casts = [
        'quantity_consumed' => 'float',
        'cost_per_unit_snapshot' => 'float',
        'cost_amount' => 'float',
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}

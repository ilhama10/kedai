<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemAddon extends Model
{
    protected $fillable = [
        'order_item_id',
        'addon_id',
        'name_snapshot',
        'price_snapshot',
    ];

    protected $casts = [
        'price_snapshot' => 'float',
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}

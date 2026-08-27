<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemVariant extends Model
{
    protected $fillable = [
        'order_item_id',
        'variant_id',
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

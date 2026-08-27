<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPromo extends Model
{
    protected $fillable = [
        'order_id',
        'promo_id',
        'promo_name_snapshot',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function promo()
    {
        return $this->belongsTo(Promo::class);
    }
}

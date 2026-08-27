<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'status',
        'amount',
        'cash_received',
        'cash_change',
        'proof_image',
        'verification_method',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'cash_received' => 'float',
        'cash_change' => 'float',
        'verified_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

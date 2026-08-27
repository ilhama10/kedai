<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = [
        'branch_id',
        'type',
        'order_id',
        'payload',
        'status',
        'attempts',
        'error_message',
        'printer_name',
        'idempotency_token',
        'is_reprint',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_reprint' => 'boolean',
        'attempts' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

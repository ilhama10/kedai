<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'branch_id',
        'inventory_id',
        'type',
        'reference_type',
        'reference_id',
        'quantity_change',
        'stock_before',
        'stock_after',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'quantity_change' => 'float',
        'stock_before' => 'float',
        'stock_after' => 'float',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

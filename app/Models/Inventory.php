<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = [
        'branch_id',
        'item_name',
        'unit',
        'stock',
        'min_stock',
        'cost_per_unit',
    ];

    protected $casts = [
        'stock' => 'float',
        'min_stock' => 'float',
        'cost_per_unit' => 'float',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}

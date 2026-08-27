<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Recipe extends Model
{
    protected $fillable = [
        'branch_id',
        'menu_id',
        'variant_id',
        'addon_id',
        'inventory_id',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($recipe) {
            $targets = array_filter([$recipe->menu_id, $recipe->variant_id, $recipe->addon_id]);
            if (count($targets) !== 1) {
                throw new InvalidArgumentException('A recipe must target EXACTLY ONE of menu_id, variant_id, or addon_id.');
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}

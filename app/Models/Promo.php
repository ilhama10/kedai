<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_discount',
        'is_stackable',
        'priority',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'is_stackable' => 'boolean',
        'discount_value' => 'float',
        'min_purchase' => 'float',
        'max_discount' => 'float',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'promo_menus');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'promo_categories');
    }
}

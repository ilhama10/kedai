<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchMenu extends Model
{
    protected $fillable = [
        'branch_id',
        'menu_id',
        'price',
        'branch_image',
        'availability',
        'status',
        'sort_order',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'price' => 'float',
    ];

    protected $appends = [
        'effective_image',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function getEffectiveImageAttribute(): string
    {
        return $this->branch_image ? asset('storage/' . $this->branch_image) : ($this->menu && $this->menu->master_image ? asset('storage/' . $this->menu->master_image) : '');
    }
}

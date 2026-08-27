<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'slug',
        'description',
        'base_price',
        'master_image',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'master_image_url',
    ];

    public function getMasterImageUrlAttribute(): ?string
    {
        return $this->master_image ? asset('storage/' . $this->master_image) : null;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function branchMenus()
    {
        return $this->hasMany(BranchMenu::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'menu_addons');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
}

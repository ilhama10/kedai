<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'name',
        'price',
        'image',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'menu_addons');
    }
}

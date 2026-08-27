<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'sort_order',
        'status',
    ];

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }
}

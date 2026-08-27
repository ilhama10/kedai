<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    protected $fillable = [
        'menu_id',
        'name',
        'price',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}

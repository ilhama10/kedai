<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'qris_image',
        'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    public function branchMenus()
    {
        return $this->hasMany(BranchMenu::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function promos()
    {
        return $this->hasMany(Promo::class);
    }

    public function printers()
    {
        return $this->hasMany(Printer::class);
    }
}

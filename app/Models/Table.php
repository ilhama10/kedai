<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    protected $fillable = [
        'branch_id',
        'table_number',
        'qr_code_token',
        'status',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

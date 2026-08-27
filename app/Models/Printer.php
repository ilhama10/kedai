<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'type',
        'connection_type',
        'connection_config',
        'paper_width',
        'status',
    ];

    protected $casts = [
        'connection_config' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

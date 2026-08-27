<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_access_token_hash',
        'branch_id',
        'table_id',
        'customer_name',
        'order_type',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'payment_status',
        'kitchen_status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'total_amount' => 'float',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderPromos()
    {
        return $this->hasMany(OrderPromo::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function printJobs()
    {
        return $this->hasMany(PrintJob::class);
    }

    public function getDerivedCustomerStatusAttribute(): string
    {
        if ($this->payment_status === 'UNPAID') {
            return 'Menunggu Pembayaran';
        }
        if ($this->payment_status === 'PENDING_VERIFICATION') {
            return 'Menunggu Verifikasi Pembayaran';
        }
        if ($this->payment_status === 'PAID') {
            switch ($this->kitchen_status) {
                case 'WAITING':
                    return 'Pesanan Diterima';
                case 'PREPARING':
                    return 'Sedang Diproses Dapur';
                case 'READY':
                    return 'Pesanan Siap';
                case 'COMPLETED':
                    return 'Pesanan Selesai';
                default:
                    return 'Pesanan Diterima';
            }
        }
        return 'Status Tidak Diketahui';
    }
}

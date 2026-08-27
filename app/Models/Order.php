<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

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

    public static function createWithUniqueOrderNumber(array $attributes, string $branchCode): self
    {
        $date = Carbon::now();
        $prefix = sprintf('%s-%s-', $branchCode, $date->format('Ymd'));

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $lastOrderNumber = static::where('branch_id', $attributes['branch_id'])
                ->where('order_number', 'like', $prefix . '%')
                ->orderByDesc('order_number')
                ->value('order_number');

            $sequence = $lastOrderNumber
                ? ((int) substr($lastOrderNumber, strrpos($lastOrderNumber, '-') + 1)) + 1
                : 1;

            try {
                return static::create(array_merge($attributes, [
                    'order_number' => sprintf('%s%04d', $prefix, $sequence),
                ]));
            } catch (QueryException $exception) {
                if ($exception->getCode() !== '23000' || $exception->errorInfo[1] !== 1062) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to generate a unique order number.');
    }

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

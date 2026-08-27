<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintJob;
use Illuminate\Support\Str;

class PrintService
{
    /**
     * Create automatic kitchen print job during atomic PAID transaction.
     * Enforces idempotency via KT-{order_id}-{hash}.
     */
    public function createAutomaticKitchenTicket(Order $order): PrintJob
    {
        $order->load(['orderItems.variants', 'orderItems.addons', 'table', 'branch']);

        $hash = md5($order->id . $order->order_number . $order->updated_at);
        $token = "KT-{$order->id}-{$hash}";

        // Check if ticket already exists (Idempotency Guard)
        $existing = PrintJob::where('idempotency_token', $token)->first();
        if ($existing) {
            return $existing;
        }

        $itemsPayload = [];
        foreach ($order->orderItems as $item) {
            $variants = $item->variants->pluck('name_snapshot')->toArray();
            $addons = $item->addons->pluck('name_snapshot')->toArray();
            $itemsPayload[] = [
                'menu_name' => $item->menu_name_snapshot,
                'quantity' => $item->quantity,
                'variants' => $variants,
                'addons' => $addons,
                'notes' => $item->notes,
            ];
        }

        $payload = [
            'type' => 'KITCHEN_TICKET',
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'order_type' => $order->order_type,
            'table_number' => $order->table ? $order->table->table_number : 'TAKE AWAY',
            'branch_name' => $order->branch->name,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'items' => $itemsPayload,
            'notes' => $order->notes,
            'is_reprint' => false,
        ];

        return PrintJob::create([
            'branch_id' => $order->branch_id,
            'type' => 'KITCHEN_TICKET',
            'order_id' => $order->id,
            'payload' => $payload,
            'status' => 'PENDING',
            'attempts' => 0,
            'idempotency_token' => $token,
            'is_reprint' => false,
        ]);
    }

    /**
     * Create manual cashier receipt print job.
     */
    public function createReceiptPrint(Order $order, string $paperWidth = '58mm'): PrintJob
    {
        $order->load(['orderItems.variants', 'orderItems.addons', 'orderPromos', 'payment', 'table', 'branch']);

        $token = "RC-{$order->id}-" . Str::random(10);

        $itemsPayload = [];
        foreach ($order->orderItems as $item) {
            $itemsPayload[] = [
                'menu_name' => $item->menu_name_snapshot,
                'unit_price' => (float)$item->unit_price_snapshot,
                'quantity' => $item->quantity,
                'total_price' => (float)$item->total_price,
                'variants' => $item->variants->pluck('name_snapshot')->toArray(),
                'addons' => $item->addons->pluck('name_snapshot')->toArray(),
                'notes' => $item->notes,
            ];
        }

        $payload = [
            'type' => 'RECEIPT',
            'paper_width' => $paperWidth,
            'branch_name' => $order->branch->name,
            'branch_address' => $order->branch->address,
            'branch_phone' => $order->branch->phone,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'order_type' => $order->order_type,
            'table_number' => $order->table ? $order->table->table_number : null,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'items' => $itemsPayload,
            'subtotal' => (float)$order->subtotal,
            'discount_amount' => (float)$order->discount_amount,
            'tax_amount' => (float)$order->tax_amount,
            'total_amount' => (float)$order->total_amount,
            'payment_method' => $order->payment ? $order->payment->method : null,
            'cash_received' => $order->payment ? (float)$order->payment->cash_received : null,
            'cash_change' => $order->payment ? (float)$order->payment->cash_change : null,
            'promos' => $order->orderPromos->pluck('promo_name_snapshot')->toArray(),
            'is_reprint' => false,
        ];

        return PrintJob::create([
            'branch_id' => $order->branch_id,
            'type' => 'RECEIPT',
            'order_id' => $order->id,
            'payload' => $payload,
            'status' => 'PENDING',
            'attempts' => 0,
            'idempotency_token' => $token,
            'is_reprint' => false,
        ]);
    }

    /**
     * Create manual kitchen ticket reprint (with *** REPRINT *** header).
     */
    public function createKitchenReprint(Order $order): PrintJob
    {
        $job = $this->createAutomaticKitchenTicket($order);

        $token = "KTR-{$order->id}-" . Str::random(10);
        $payload = $job->payload;
        $payload['header'] = '*** REPRINT ***';
        $payload['is_reprint'] = true;

        return PrintJob::create([
            'branch_id' => $order->branch_id,
            'type' => 'KITCHEN_TICKET',
            'order_id' => $order->id,
            'payload' => $payload,
            'status' => 'PENDING',
            'attempts' => 0,
            'idempotency_token' => $token,
            'is_reprint' => true,
        ]);
    }

    /**
     * Retry a failed print job.
     */
    public function retryPrintJob(PrintJob $printJob): PrintJob
    {
        $printJob->status = 'PENDING';
        $printJob->attempts = $printJob->attempts + 1;
        $printJob->error_message = null;
        $printJob->save();

        return $printJob;
    }
}

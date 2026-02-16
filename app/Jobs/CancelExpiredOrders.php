<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CancelExpiredOrders
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Order::where('status', 'confirmed')
            ->where('payment_type', 'cash')
            ->whereIn('order_type', ['dine', 'pickup'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunk(100, function ($orders) {
                foreach ($orders as $order) {
                    $order->update(['status' => 'cancelled']);
                    
                    if ($order->invoice) {
                        $order->invoice->update(['payment_status' => 'failed']);
                    }
                }
            });
    }
}

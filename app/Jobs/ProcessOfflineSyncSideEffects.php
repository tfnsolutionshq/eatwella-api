<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessOfflineSyncSideEffects implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries = 3;

    /**
     * @param string[] $orderIds Array of order UUIDs created during offline sync
     * @param int $reconciledCount Number of orders that required stock reconciliation
     */
    public function __construct(
        public readonly array $orderIds,
        public readonly int $reconciledCount = 0
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        if (empty($this->orderIds)) {
            return;
        }

        $orders = Order::with(['user', 'outlet', 'orderItems'])->whereIn('id', $this->orderIds)->get();

        foreach ($orders as $order) {
            try {
                // Customer notification if registered customer
                if ($order->user_id) {
                    $notificationService->sendOrderNotification($order->user_id, NotificationService::ORDER_PLACED, $order);
                    $notificationService->sendOrderNotification($order->user_id, NotificationService::ORDER_COMPLETED_CUSTOMER, $order);
                }

                // Notify admin/staff for order placed
                $notificationService->notifyAdminsForNewOrder($order);
            } catch (\Throwable $e) {
                Log::error("Failed sending notification for offline order {$order->id}: " . $e->getMessage());
            }
        }
    }
}

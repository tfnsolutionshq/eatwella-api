<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    // ─── Notification type constants ─────────────────────────────────────────

    const ORDER_PLACED              = 'order_placed';
    const ORDER_PLACED_ADMIN        = 'order_placed_admin';
    const ORDER_CONFIRMED           = 'order_confirmed';
    const ORDER_DISPATCHED_CUSTOMER = 'order_dispatched_customer';
    const ORDER_DISPATCHED_AGENT    = 'order_dispatched_agent';
    const ORDER_COMPLETED_CUSTOMER  = 'order_completed_customer';
    const ORDER_COMPLETED_AGENT     = 'order_completed_agent';
    const ORDER_COMPLETED_ADMIN     = 'order_completed_admin';
    const ORDER_CANCELLED           = 'order_cancelled';

    // ─── Message definitions ─────────────────────────────────────────────────

    private function definitions(): array
    {
        return [
            self::ORDER_PLACED => [
                'title' => 'Order Placed 🎉',
                'body'  => 'Your order #:number has been received! We\'re getting it ready.',
            ],
            self::ORDER_CONFIRMED => [
                'title' => 'Order Confirmed ✅',
                'body'  => 'Your order #:number has been confirmed.',
            ],
            self::ORDER_DISPATCHED_CUSTOMER => [
                'title' => 'On the Way 🚴',
                'body'  => 'Your order #:number is on its way to you!',
            ],
            self::ORDER_DISPATCHED_AGENT => [
                'title' => 'New Delivery Assigned 📦',
                'body'  => 'Order #:number has been assigned to you for delivery.',
            ],
            self::ORDER_COMPLETED_CUSTOMER => [
                'title' => 'Delivered! 🎉',
                'body'  => 'Your order #:number has been delivered. Enjoy your meal!',
            ],
            self::ORDER_COMPLETED_AGENT => [
                'title' => 'Delivery Confirmed ✅',
                'body'  => 'Delivery for order #:number has been confirmed by the customer.',
            ],
            self::ORDER_CANCELLED => [
                'title' => 'Order Cancelled ❌',
                'body'  => 'Your order #:number has been cancelled.',
            ],
            self::ORDER_PLACED_ADMIN => [
                'title' => 'New Order Received 🛎️',
                'body'  => 'Order #:number (:order_type) placed at :outlet_name.',
            ],
            self::ORDER_COMPLETED_ADMIN => [
                'title' => 'Order Completed ✅',
                'body'  => 'Order #:number (:order_type) at :outlet_name has been completed.',
            ],
        ];
    }

    // ─── Core notify method ──────────────────────────────────────────────────

    /**
     * Persist in-app notification(s) and dispatch a push notification job.
     *
     * @param  User|Collection|User[]  $recipients
     */
    public function notify(
        User|Collection|array $recipients,
        string $type,
        array $data = []
    ): void {
        $definitions = $this->definitions();

        if (! isset($definitions[$type])) {
            return;
        }

        $title = $definitions[$type]['title'];
        $body  = $this->interpolate($definitions[$type]['body'], $data);

        $recipients = $recipients instanceof User ? collect([$recipients]) : collect($recipients);

        foreach ($recipients as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type'    => $type,
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
            ]);
        }

        // Dispatch push job (non-blocking — runs on the queue)
        SendPushNotification::dispatch($recipients->pluck('id')->toArray(), $title, $body, $data);
    }

    // ─── Order-specific helpers ───────────────────────────────────────────────

    public function orderPlaced(Order $order): void
    {
        $data = $this->orderData($order);

        // Notify customer
        if ($order->user_id) {
            $customer = User::find($order->user_id);
            if ($customer) {
                $this->notify($customer, self::ORDER_PLACED, $data);
            }
        }

        // Notify all admins
        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            $this->notify($admins, self::ORDER_PLACED_ADMIN, $data);
        }
    }

    public function orderConfirmed(Order $order): void
    {
        if (! $order->user_id) return;
        $customer = User::find($order->user_id);
        if ($customer) {
            $this->notify($customer, self::ORDER_CONFIRMED, $this->orderData($order));
        }
    }

    public function orderDispatched(Order $order): void
    {
        $data = $this->orderData($order);

        // Notify customer
        if ($order->user_id) {
            $customer = User::find($order->user_id);
            if ($customer) {
                $this->notify($customer, self::ORDER_DISPATCHED_CUSTOMER, $data);
            }
        }

        // Notify delivery agent
        if ($order->delivery_agent_id) {
            $agent = User::find($order->delivery_agent_id);
            if ($agent) {
                $this->notify($agent, self::ORDER_DISPATCHED_AGENT, $data);
            }
        }
    }

    public function orderCompleted(Order $order): void
    {
        $data = $this->orderData($order);

        // Notify customer
        if ($order->user_id) {
            $customer = User::find($order->user_id);
            if ($customer) {
                $this->notify($customer, self::ORDER_COMPLETED_CUSTOMER, $data);
            }
        }

        // Notify delivery agent (for delivery orders)
        if ($order->delivery_agent_id) {
            $agent = User::find($order->delivery_agent_id);
            if ($agent) {
                $this->notify($agent, self::ORDER_COMPLETED_AGENT, $data);
            }
        }

        // Notify all admins
        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            $this->notify($admins, self::ORDER_COMPLETED_ADMIN, $data);
        }
    }

    public function orderCancelled(Order $order): void
    {
        if (! $order->user_id) return;
        $customer = User::find($order->user_id);
        if ($customer) {
            $this->notify($customer, self::ORDER_CANCELLED, $this->orderData($order));
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function orderData(Order $order): array
    {
        $order->loadMissing('outlet');

        return [
            'order_id'     => (string) $order->id,
            'order_number' => $order->order_number,
            'order_type'   => $order->order_type,
            'outlet_id'    => $order->outlet ? (string) $order->outlet->id : null,
            'outlet_name'  => $order->outlet?->name ?? 'N/A',
            'number'       => $order->order_number,
        ];
    }

    private function interpolate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace(':' . $key, $value, $template);
        }
        return $template;
    }
}

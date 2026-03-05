<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderCompleted;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin', 'cashier'])) {
            return $response;
        }
        $query = Order::with(['orderItems.menu', 'user.addresses', 'cashier', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function show(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['admin', 'cashier'])) {
            return $response;
        }
        return $order->load(['orderItems.menu', 'invoice', 'user.addresses', 'cashier', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name']);
    }

    public function update(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['admin', 'cashier'])) {
            return $response;
        }
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled'
        ]);

        $originalStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        // Clear expiry when admin marks as completed
        if ($validated['status'] === 'completed') {
            $order->update(['expires_at' => null]);

            if ($originalStatus !== 'completed') {
                Mail::to($order->customer_email)->send(new OrderCompleted($order));

                // Award Loyalty Points if registered user and points not yet awarded
                if ($order->user_id && $order->points_earned == 0) {
                    $pointsPerOrder = (int) (\App\Models\Setting::where('key', 'loyalty_points_per_order')->value('value') ?? 10);
                    $user = \App\Models\User::find($order->user_id);
                    if ($user) {
                        $user->increment('loyalty_points', $pointsPerOrder);
                        $order->update(['points_earned' => $pointsPerOrder]);
                    }
                }
            }
        }

        return response()->json($order);
    }

    public function cashierOrders(Request $request)
    {
        if ($response = $this->requireRole($request, ['cashier'])) {
            return $response;
        }

        $query = Order::with(['orderItems.menu', 'invoice', 'user.addresses', 'cashier', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name'])
            ->where('cashier_id', $request->user()->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function cashierCreatedOrders(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = Order::with(['orderItems.menu', 'invoice', 'user.addresses', 'cashier', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name'])
            ->whereNotNull('cashier_id');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }
}

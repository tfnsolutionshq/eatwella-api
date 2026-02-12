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
        $query = Order::with('orderItems.menu');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function show(Order $order)
    {
        return $order->load(['orderItems.menu', 'invoice']);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled'
        ]);

        $originalStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        if ($validated['status'] === 'completed' && $originalStatus !== 'completed') {
            Mail::to($order->customer_email)->send(new OrderCompleted($order));
        }

        return response()->json($order);
    }
}

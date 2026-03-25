<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    /**
     * Get a list of confirmed orders for the kitchen to prepare.
     */
    public function getConfirmedOrders(Request $request)
    {
        if ($response = $this->requireRole($request, ['kitchen'])) {
            return $response;
        }

        $orders = Order::with(['orderItems.menu'])
            ->where('status', 'confirmed')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Mark one or more orders as ready.
     */
    public function markAsReady(Request $request)
    {
        if ($response = $this->requireRole($request, ['kitchen'])) {
            return $response;
        }

        $validated = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'required|uuid|exists:orders,id',
        ]);

        $updatedCount = Order::whereIn('id', $validated['order_ids'])
            ->where('status', 'confirmed')
            ->update(['status' => 'ready']);

        return response()->json([
            'message' => "Successfully marked {$updatedCount} order(s) as ready.",
            'updated_count' => $updatedCount
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgentBankDetail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryAgentController extends Controller
{
    public function overview(Request $request)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        $query = Order::where('delivery_agent_id', $request->user()->id)
            ->where('status', 'completed');

        return response()->json([
            'total_deliveries' => $query->count(),
            'total_earnings'   => (float) $query->sum('delivery_fee'),
        ]);
    }

    public function getOrders(Request $request)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        $rider   = $request->user();
        $filter  = $request->get('status', 'available');
        $perPage = $request->get('per_page', 15);

        $select = [
            'orders.id', 'orders.order_number', 'orders.delivery_fee',
            'orders.delivery_address', 'orders.delivery_agent_id',
            'orders.status', 'orders.assigned_at',
        ];

        $format = fn($order) => [
            'id'                  => $order->id,
            'order_number'        => $order->order_number,
            'delivery_fee'        => (float) $order->delivery_fee,
            'total_items'         => (int) $order->total_items,
            'restaurant_location' => 'Unizik',
            'delivery_address'    => $order->delivery_address,
            'status'              => $order->status,
        ];

        if ($filter === 'available') {
            $orders = Order::select($select)
                ->withSum('orderItems as total_items', 'quantity')
                ->where('order_type', 'delivery')
                ->where('status', 'ready')
                ->whereNull('delivery_agent_id')
                ->latest()
                ->paginate($perPage);

            $orders->getCollection()->transform($format);
            return response()->json($orders);
        }

        if ($filter === 'active') {
            $orders = Order::select($select)
                ->withSum('orderItems as total_items', 'quantity')
                ->where('delivery_agent_id', $rider->id)
                ->where('status', 'dispatched')
                ->latest()
                ->paginate($perPage);

            $orders->getCollection()->transform($format);
            return response()->json($orders);
        }

        if ($filter === 'completed') {
            $orders = Order::select($select)
                ->withSum('orderItems as total_items', 'quantity')
                ->where('delivery_agent_id', $rider->id)
                ->where('status', 'completed')
                ->latest('completed_at')
                ->paginate($perPage);

            $orders->getCollection()->transform($format);
            return response()->json($orders);
        }

        // all: available + this rider's active/completed + ignored by this rider
        $ignoredOrderIds = DB::table('rider_ignored_orders')
            ->where('user_id', $rider->id)
            ->pluck('order_id');

        $orders = Order::select($select)
            ->withSum('orderItems as total_items', 'quantity')
            ->where('order_type', 'delivery')
            ->where(function ($q) use ($rider, $ignoredOrderIds) {
                $q->where(fn($q) => $q->where('status', 'ready')->whereNull('delivery_agent_id'))         // available
                  ->orWhere('delivery_agent_id', $rider->id)                                               // mine (active/completed)
                  ->orWhereIn('id', $ignoredOrderIds);                                                     // ignored by me
            })
            ->latest()
            ->paginate($perPage);

        $orders->getCollection()->transform($format);
        return response()->json($orders);
    }

    public function acceptOrder(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        if ($order->order_type !== 'delivery') {
            return response()->json(['message' => 'This is not a delivery order.'], 422);
        }

        if ($order->status !== 'ready') {
            return response()->json(['message' => 'Order is no longer available.'], 422);
        }

        if ($order->delivery_agent_id && $order->delivery_agent_id !== $request->user()->id) {
            return response()->json(['message' => 'Order has already been accepted by another rider.'], 422);
        }

        $order->update([
            'delivery_agent_id'         => $request->user()->id,
            'assigned_by_supervisor_id' => null,
            'assigned_at'               => now(),
            'status'                    => 'dispatched',
        ]);

        // remove ignore record if exists
        \DB::table('rider_ignored_orders')
            ->where('user_id', $request->user()->id)
            ->where('order_id', $order->id)
            ->delete();

        return response()->json(['message' => 'Order accepted. Go make that delivery!']);
    }

    public function ignoreOrder(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        if ($order->status !== 'ready' || $order->delivery_agent_id !== null) {
            return response()->json(['message' => 'Order is no longer available.'], 422);
        }

        \DB::table('rider_ignored_orders')->insertOrIgnore([
            'user_id'    => $request->user()->id,
            'order_id'   => $order->id,
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Order ignored.']);
    }

    // ─── Bank Details CRUD ───────────────────────────────────────────────────

    public function getBankDetails(Request $request)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        $details = DeliveryAgentBankDetail::where('user_id', $request->user()->id)->get();

        return response()->json(['status' => true, 'data' => $details]);
    }

    public function storeBankDetail(Request $request)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        $validated = $request->validate([
            'bank_name'      => 'required|string|max:255',
            'account_name'   => 'required|string|max:255',
            'account_number' => 'required|string|max:20',
        ]);

        $detail = DeliveryAgentBankDetail::create([
            'user_id'        => $request->user()->id,
            'bank_name'      => $validated['bank_name'],
            'account_name'   => $validated['account_name'],
            'account_number' => $validated['account_number'],
        ]);

        return response()->json(['status' => true, 'data' => $detail], 201);
    }

    public function updateBankDetail(Request $request, DeliveryAgentBankDetail $bankDetail)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        if ($bankDetail->user_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'bank_name'      => 'sometimes|string|max:255',
            'account_name'   => 'sometimes|string|max:255',
            'account_number' => 'sometimes|string|max:20',
        ]);

        $bankDetail->update($validated);

        return response()->json(['status' => true, 'data' => $bankDetail]);
    }

    public function deleteBankDetail(Request $request, DeliveryAgentBankDetail $bankDetail)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        if ($bankDetail->user_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => 'Not found.'], 404);
        }

        $bankDetail->delete();

        return response()->json(['status' => true, 'message' => 'Bank detail deleted successfully.']);
    }

    // ─── Admin: Orders delivered by a rider ──────────────────────────────────

    public function riderOrders(Request $request, User $user)
    {
        if ($response = $this->requireRole($request, ['admin', 'supervisor'])) {
            return $response;
        }

        if ($user->role !== 'delivery_agent') {
            return response()->json(['status' => false, 'message' => 'User is not a delivery agent.'], 422);
        }

        $request->validate([
            'date'       => 'nullable|date',
            'date_from'  => 'nullable|date',
            'date_to'    => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Order::with(['orderItems.menu', 'orderItems.packaging', 'invoice', 'deliveryZone.city.state'])
            ->where('delivery_agent_id', $user->id)
            ->where('status', 'completed');

        if ($request->filled('date')) {
            $query->whereDate('completed_at', $request->date);
        } elseif ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('completed_at', [$request->date_from, $request->date_to . ' 23:59:59']);
        } elseif ($request->filled('date_from')) {
            $query->whereDate('completed_at', '>=', $request->date_from);
        } elseif ($request->filled('date_to')) {
            $query->whereDate('completed_at', '<=', $request->date_to);
        }

        $orders = $query->latest('completed_at')->paginate($request->get('per_page', 15));

        $bankDetails = DeliveryAgentBankDetail::where('user_id', $user->id)->get();

        return response()->json([
            'status'       => true,
            'agent'        => $user->only(['id', 'name', 'email', 'phone']),
            'bank_details' => $bankDetails,
            'orders'       => $orders,
        ]);
    }
}

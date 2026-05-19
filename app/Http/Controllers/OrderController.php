<?php

namespace App\Http\Controllers;

use App\Mail\OrderAssignedToAgent;
use App\Mail\OrderCancelled;
use App\Mail\OrderCompleted;
use App\Models\InventoryLog;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin', 'attendant'])) {
            return $response;
        }
        $query = Order::with(['orderItems.menu', 'orderItems.packaging', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $this->applySearch($query, $request);

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function show(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['admin', 'attendant', 'kitchen'])) {
            return $response;
        }

        return $order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'deliveryZone.city.state', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name']);
    }

    public function update(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['admin', 'attendant', 'supervisor'])) {
            return $response;
        }

        $user = $request->user();

        // Attendant can only update their own orders or unassigned orders; other attendants and supervisor can update for them
        // Actually, since we want attendants to be able to confirm cash orders placed by customers online,
        // we shouldn't restrict them to only orders they created.
        // We'll remove this restriction so they can process any order.

        $validated = $request->validate([
            'status' => 'required|in:pending,processing,confirmed,in_kitchen,ready,dispatched,completed,cancelled',
        ]);

        $originalStatus = $order->status;

        // Prevent cancellation of gateway-confirmed orders
        if ($validated['status'] === 'cancelled') {
            if (! in_array($user->role, ['admin', 'supervisor'], true)) {
                return response()->json(['status' => false, 'message' => 'Only admins and supervisors can cancel orders.'], 403);
            }
            if ($originalStatus === 'confirmed' && $order->payment_type === 'gateway') {
                return response()->json(['status' => false, 'message' => 'Gateway-confirmed orders cannot be cancelled.'], 422);
            }

            $order->update(['status' => 'cancelled']);

            // Restore stock if order was confirmed (stock was already deducted)
            if ($originalStatus === 'confirmed') {
                $order->loadMissing('orderItems');
                foreach ($order->orderItems as $item) {
                    $menu = Menu::find($item->menu_id);
                    if ($menu && $menu->stock_quantity !== null) {
                        $before = $menu->stock_quantity;
                        $after  = $before + $item->quantity;
                        $menu->update(['stock_quantity' => $after, 'is_available' => true]);
                        InventoryLog::create([
                            'menu_id'          => $menu->id,
                            'user_id'          => $user->id,
                            'type'             => 'restock',
                            'quantity_before'  => $before,
                            'quantity_changed' => $item->quantity,
                            'quantity_after'   => $after,
                            'note'             => 'Stock restored due to order cancellation #' . $order->order_number,
                        ]);
                    }
                }

                // Restore loyalty points if paid with loyalty points
                if ($order->payment_type === 'loyalty_points' && $order->user_id) {
                    $loyalty = app(LoyaltyService::class);
                    $pointsToRestore = $loyalty->nairaToPoints($order->final_amount);
                    User::where('id', $order->user_id)->increment('loyalty_points', $pointsToRestore);
                }
            }

            // Send cancellation email
            try {
                Mail::to($order->customer_email)->send(new OrderCancelled($order->load('orderItems.menu')));
            } catch (\Exception $e) {
                Log::error('Failed to send cancellation email: ' . $e->getMessage());
            }

            return response()->json(['status' => true, 'message' => 'Order cancelled successfully.', 'order' => $order]);
        }

        $updateData = ['status' => $validated['status']];

        // If status is changed from pending to confirmed, update invoice to paid and deduct stock
        if ($validated['status'] === 'confirmed' && $originalStatus === 'pending') {
            if ($order->invoice) {
                $order->invoice->update(['payment_status' => 'paid']);
            }
            $updateData['expires_at'] = null;
            $order->update($updateData);
            $order->deductItemsStock();
        } else {
            if ($validated['status'] === 'completed') {
                $updateData['expires_at']      = null;
                $updateData['completed_by_id'] = $user->id;
                $updateData['completed_at']    = now();
            }
            $order->update($updateData);
        }

        if ($validated['status'] === 'completed' && $originalStatus !== 'completed') {
            Mail::to($order->customer_email)->send(new OrderCompleted($order));
            $this->awardLoyaltyPoints($order);
        }

        return response()->json($order->load('completedBy'));
    }

    public function sendToKitchen(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['admin', 'attendant', 'supervisor'])) {
            return $response;
        }

        if ($order->status !== 'confirmed') {
            return response()->json(['message' => 'Only confirmed orders can be sent to kitchen'], 422);
        }

        $order->update([
            'status'                => 'in_kitchen',
            'sent_to_kitchen_by_id' => $request->user()->id,
            'sent_to_kitchen_at'    => now(),
        ]);

        return response()->json($order->load('sentToKitchenBy'));
    }

    private function applySearch($query, Request $request)
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhereHas('invoice', fn($q) => $q->where('invoice_number', 'like', "%{$search}%"));
            });
        }
        return $query;
    }

    private function awardLoyaltyPoints(Order $order): void
    {
        if (! $order->user_id || $order->points_earned || $order->payment_type === 'loyalty_points') {
            return;
        }

        $loyalty = app(LoyaltyService::class);
        $points = $loyalty->calculatePointsForAmount($order->final_amount);

        if ($points <= 0) return;

        $customer = User::find($order->user_id);
        if ($customer) {
            $customer->increment('loyalty_points', $points);
            $order->update(['points_earned' => $points]);
        }
    }

    public function supervisorIndex(Request $request)
    {
        if ($response = $this->requireRole($request, ['supervisor'])) {
            return $response;
        }

        $query = Order::with(['orderItems.menu', 'orderItems.packaging', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'completedBy', 'invoice']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('order_type')) {
            $query->where('order_type', $request->order_type);
        }

        if ($request->filled('assigned')) {
            if ($request->assigned === '1') {
                $query->whereNotNull('delivery_agent_id');
            } elseif ($request->assigned === '0') {
                $query->whereNull('delivery_agent_id');
            }
        }

        $this->applySearch($query, $request);

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function getDeliveryAgents(Request $request)
    {
        if ($response = $this->requireRole($request, ['supervisor', 'admin'])) {
            return $response;
        }

        $agents = User::where('role', 'delivery_agent')
            ->select('id', 'name', 'email', 'phone')
            ->orderBy('name')
            ->get();

        return response()->json($agents);
    }

    public function supervisorShow(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['supervisor'])) {
            return $response;
        }

        return $order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'completedBy', 'deliveryZone.city.state']);
    }

    public function assignDeliveryAgent(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['supervisor', 'admin'])) {
            return $response;
        }

        if ($order->order_type !== 'delivery') {
            return response()->json(['message' => 'Only delivery orders can be assigned'], 422);
        }

        $validated = $request->validate([
            'delivery_agent_id' => 'required|exists:users,id',
        ]);

        $agent = User::findOrFail($validated['delivery_agent_id']);
        if ($agent->role !== 'delivery_agent') {
            return response()->json(['message' => 'User is not a delivery agent'], 422);
        }

        $order->update([
            'delivery_agent_id' => $agent->id,
            'assigned_by_supervisor_id' => $request->user()->id,
            'assigned_at' => now(),
            'status' => 'dispatched',
        ]);

        if ($agent->email) {
            Mail::to($agent->email)->send(new OrderAssignedToAgent($order, $agent));
        }

        return response()->json($order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'deliveryZone.city.state']));
    }

    public function deliveryAgentShow(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        if ($order->delivery_agent_id !== $request->user()->id) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return $order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'deliveryZone.city.state']);
    }

    public function completeDelivery(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        if ($order->delivery_agent_id !== $request->user()->id) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->status === 'completed') {
            return response()->json(['message' => 'Order already completed'], 422);
        }

        $request->validate([
            'delivery_pin' => 'required|string|size:6',
            'note'         => 'nullable|string|max:500',
        ]);

        if ($order->delivery_pin !== $request->delivery_pin) {
            return response()->json(['message' => 'Invalid delivery PIN'], 400);
        }

        $order->update([
            'status'                => 'completed',
            'delivery_note'         => $request->note,
            'completed_by_id'       => $request->user()->id,
            'completed_at'          => now(),
            'expires_at'            => null,
        ]);

        $this->awardLoyaltyPoints($order);

        return response()->json($order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'deliveryAgent', 'assignedBySupervisor', 'completedBy']));
    }

    public function adminCompleteDelivery(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if ($order->order_type !== 'delivery') {
            return response()->json(['message' => 'Only delivery orders can be completed here'], 422);
        }

        if ($order->status === 'completed') {
            return response()->json(['message' => 'Order already completed'], 422);
        }

        $request->validate([
            'delivery_pin' => 'required|string|size:6',
            'note'         => 'nullable|string|max:500',
        ]);

        if ($order->delivery_pin !== $request->delivery_pin) {
            return response()->json(['message' => 'Invalid delivery PIN'], 400);
        }

        $order->update([
            'status'          => 'completed',
            'delivery_note'   => $request->note,
            'completed_by_id' => $request->user()->id,
            'completed_at'    => now(),
            'expires_at'      => null,
        ]);

        $this->awardLoyaltyPoints($order);

        return response()->json($order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'deliveryAgent', 'assignedBySupervisor', 'completedBy']));
    }

    public function supervisorCompleteDelivery(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['supervisor', 'admin'])) {
            return $response;
        }

        if ($order->order_type !== 'delivery') {
            return response()->json(['message' => 'Only delivery orders can be completed here'], 422);
        }

        if ($order->status === 'completed') {
            return response()->json(['message' => 'Order already completed'], 422);
        }

        $request->validate([
            'delivery_pin' => 'required|string|size:6',
            'note'         => 'nullable|string|max:500',
        ]);

        if ($order->delivery_pin !== $request->delivery_pin) {
            return response()->json(['message' => 'Invalid delivery PIN'], 400);
        }

        $order->update([
            'status'                => 'completed',
            'delivery_note'         => $request->note,
            'completed_by_id'       => $request->user()->id,
            'completed_at'          => now(),
            'expires_at'            => null,
        ]);

        $this->awardLoyaltyPoints($order);

        return response()->json($order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'deliveryAgent', 'assignedBySupervisor', 'completedBy']));
    }

    public function deliveryAgentOrders(Request $request)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        $query = Order::with(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor'])
            ->where('delivery_agent_id', $request->user()->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function attendantOrders(Request $request)
    {
        if ($response = $this->requireRole($request, ['attendant'])) {
            return $response;
        }

        $query = Order::with(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $this->applySearch($query, $request);

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function attendantCreatedOrders(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = Order::with(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name'])
            ->whereNotNull('attendant_id');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $this->applySearch($query, $request);

        return $query->latest()->paginate($request->get('per_page', 15));
    }
}

<?php

namespace App\Http\Controllers;

use App\Mail\OrderCompleted;
use App\Models\Order;
use Illuminate\Http\Request;
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

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function show(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['admin', 'attendant', 'kitchen'])) {
            return $response;
        }

        return $order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name']);
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

        $updateData = ['status' => $validated['status']];

        // If status is changed from pending to confirmed, update invoice to paid
        if ($validated['status'] === 'confirmed' && $originalStatus === 'pending') {
            if ($order->invoice) {
                $order->invoice->update(['payment_status' => 'paid']);
            }
            $updateData['expires_at'] = null; // Remove expiration if it was pending cash
        }

        if ($validated['status'] === 'completed') {
            $updateData['expires_at']      = null;
            $updateData['completed_by_id'] = $user->id;
            $updateData['completed_at']    = now();
        }

        $order->update($updateData);

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

    private function awardLoyaltyPoints(Order $order): void
    {
        if (! $order->user_id || $order->points_earned) {
            return;
        }

        $pointsPerOrder = (int) (\App\Models\Setting::where('key', 'loyalty_points_per_order')->value('value') ?? 10);
        $customer = \App\Models\User::find($order->user_id);
        if ($customer) {
            $customer->increment('loyalty_points', $pointsPerOrder);
            $order->update(['points_earned' => $pointsPerOrder]);
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

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function getDeliveryAgents(Request $request)
    {
        if ($response = $this->requireRole($request, ['supervisor', 'admin'])) {
            return $response;
        }

        $agents = \App\Models\User::where('role', 'delivery_agent')
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

        return $order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor', 'completedBy']);
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

        $agent = \App\Models\User::findOrFail($validated['delivery_agent_id']);
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
            Mail::to($agent->email)->send(new \App\Mail\OrderAssignedToAgent($order, $agent));
        }

        return response()->json($order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor']));
    }

    public function deliveryAgentShow(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['delivery_agent'])) {
            return $response;
        }

        if ($order->delivery_agent_id !== $request->user()->id) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return $order->load(['orderItems.menu', 'orderItems.packaging', 'invoice', 'user.addresses', 'attendant', 'deliveryAgent', 'assignedBySupervisor']);
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

    public function supervisorCompleteDelivery(Request $request, Order $order)
    {
        if ($response = $this->requireRole($request, ['supervisor'])) {
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

        return $query->latest()->paginate($request->get('per_page', 15));
    }
}

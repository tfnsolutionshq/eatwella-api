<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgentBankDetail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class DeliveryAgentController extends Controller
{
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

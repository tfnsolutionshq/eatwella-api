<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use Illuminate\Http\Request;

class PaymentAccountController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin', 'attendant', 'supervisor'])) {
            return $response;
        }

        $accounts = PaymentAccount::orderBy('type')->orderBy('sort_order')->get();

        return response()->json($accounts->groupBy('type'));
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'type'           => 'required|in:transfer,pos,cash',
            'label'          => 'required|string|max:255',
            'account_name'   => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'terminal_id'    => 'nullable|string|max:255',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer|min:0',
        ]);

        $account = PaymentAccount::create($validated);

        return response()->json($account, 201);
    }

    public function update(Request $request, PaymentAccount $paymentAccount)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'type'           => 'sometimes|in:transfer,pos,cash',
            'label'          => 'sometimes|string|max:255',
            'account_name'   => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'terminal_id'    => 'nullable|string|max:255',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer|min:0',
        ]);

        $paymentAccount->update($validated);

        return response()->json($paymentAccount);
    }

    public function destroy(Request $request, PaymentAccount $paymentAccount)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $paymentAccount->delete();

        return response()->json(['message' => 'Payment account deleted successfully.']);
    }

    public function toggle(Request $request, PaymentAccount $paymentAccount)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $paymentAccount->update(['is_active' => ! $paymentAccount->is_active]);

        return response()->json([
            'message'   => 'Payment account ' . ($paymentAccount->is_active ? 'activated' : 'deactivated') . '.',
            'is_active' => $paymentAccount->is_active,
        ]);
    }

    public function staffIndex(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin', 'attendant', 'supervisor'])) {
            return $response;
        }

        $accounts = PaymentAccount::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($accounts->groupBy('type'));
    }
}

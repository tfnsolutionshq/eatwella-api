<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = Discount::withCount('users');

        if ($request->filled('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'discount_type' => 'required|in:menu,free_delivery',
            'type'          => 'required_if:discount_type,menu|in:percentage,fixed',
            'value'         => 'required_if:discount_type,menu|numeric|min:0',
            'start_date'    => 'required|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'is_indefinite' => 'boolean',
            'is_active'     => 'boolean',
            'usage_limit'   => 'nullable|integer|min:1',
            'applies_to'    => 'required|in:all,specific',
            'user_ids'      => 'required_if:applies_to,specific|array',
            'user_ids.*'    => 'exists:users,id',
        ]);

        if ($validated['discount_type'] === 'free_delivery') {
            $validated['type']  = 'fixed';
            $validated['value'] = 0;
        }

        $validated['code'] = $this->generateUniqueCode();

        $appliesTo = $validated['applies_to'];
        $userIds   = $validated['user_ids'] ?? [];
        unset($validated['applies_to'], $validated['user_ids']);

        $discount = Discount::create($validated);

        if ($appliesTo === 'specific' && !empty($userIds)) {
            $discount->users()->sync($userIds);
        }

        return response()->json($discount->load('users:id,name,email'), 201);
    }

    public function show(Request $request, Discount $discount)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        return $discount->load('users:id,name,email');
    }

    public function update(Request $request, Discount $discount)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name'          => 'string|max:255',
            'discount_type' => 'in:menu,free_delivery',
            'type'          => 'in:percentage,fixed',
            'value'         => 'numeric|min:0',
            'start_date'    => 'date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'is_indefinite' => 'boolean',
            'is_active'     => 'boolean',
            'usage_limit'   => 'nullable|integer|min:1',
            'applies_to'    => 'sometimes|in:all,specific',
            'user_ids'      => 'required_if:applies_to,specific|array',
            'user_ids.*'    => 'exists:users,id',
        ]);

        $appliesTo = $validated['applies_to'] ?? null;
        $userIds   = $validated['user_ids'] ?? [];
        unset($validated['applies_to'], $validated['user_ids']);

        $discount->update($validated);

        if ($appliesTo === 'all') {
            $discount->users()->detach();
        } elseif ($appliesTo === 'specific' && !empty($userIds)) {
            $discount->users()->sync($userIds);
        }

        return response()->json($discount->load('users:id,name,email'));
    }

    public function destroy(Request $request, Discount $discount)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $discount->delete();
        return response()->json(['message' => 'Discount deleted']);
    }

    public function validateCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $discount = Discount::where('code', strtoupper($request->code))->first();

        if (!$discount) {
            return response()->json(['message' => 'Invalid discount code'], 404);
        }

        if (!$discount->isValid()) {
            return response()->json(['message' => 'Discount code is not valid or has expired'], 400);
        }

        // Check user-specific restriction
        $user = auth('sanctum')->user();
        if (!$discount->isForUser($user)) {
            return response()->json(['message' => 'This discount code is not valid for your account'], 403);
        }

        return response()->json(['valid' => true, 'discount' => $discount]);
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
        } while (Discount::where('code', $code)->exists());

        return $code;
    }
}

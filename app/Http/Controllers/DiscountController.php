<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        return Discount::paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_indefinite' => 'boolean',
            'is_active' => 'boolean',
            'usage_limit' => 'nullable|integer|min:1'
        ]);

        // Generate unique code
        $validated['code'] = $this->generateUniqueCode();

        $discount = Discount::create($validated);
        return response()->json($discount, 201);
    }

    protected function generateUniqueCode()
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
        } while (Discount::where('code', $code)->exists());
        
        return $code;
    }

    public function show(Discount $discount)
    {
        return $discount;
    }

    public function update(Request $request, Discount $discount)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'type' => 'in:percentage,fixed',
            'value' => 'numeric|min:0',
            'start_date' => 'date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_indefinite' => 'boolean',
            'is_active' => 'boolean',
            'usage_limit' => 'nullable|integer|min:1'
        ]);

        $discount->update($validated);
        return response()->json($discount);
    }

    public function destroy(Discount $discount)
    {
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

        return response()->json([
            'valid' => true,
            'discount' => $discount
        ]);
    }
}

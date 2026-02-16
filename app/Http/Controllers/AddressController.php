<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses;
        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'street_address' => 'required|string|max:255',
            'state' => 'required|string|max:100',
            'closest_landmark' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $address = $request->user()->addresses()->create($validated);

        return response()->json([
            'message' => 'Address added successfully',
            'address' => $address
        ], 201);
    }

    public function update(Request $request, Address $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'street_address' => 'required|string|max:255',
            'state' => 'required|string|max:100',
            'closest_landmark' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $address->update($validated);

        return response()->json([
            'message' => 'Address updated successfully',
            'address' => $address
        ]);
    }

    public function destroy(Request $request, Address $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted successfully']);
    }
}

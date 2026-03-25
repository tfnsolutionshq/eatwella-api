<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->addresses()->with('zone.city')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'zone_id'           => 'required|exists:zones,id',
            'street_address'    => 'required|string|max:255',
            'closest_landmark'  => 'nullable|string|max:255',
        ]);

        $zone = \App\Models\Zone::findOrFail($validated['zone_id']);
        if (! $zone->is_active) {
            return response()->json(['message' => 'Selected zone is not available for delivery'], 422);
        }

        $address = $request->user()->addresses()->create($validated);

        return response()->json([
            'message' => 'Address added successfully',
            'address' => $address->load('zone.city'),
        ], 201);
    }

    public function update(Request $request, Address $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'zone_id'           => 'required|exists:zones,id',
            'street_address'    => 'required|string|max:255',
            'closest_landmark'  => 'nullable|string|max:255',
        ]);

        $zone = \App\Models\Zone::findOrFail($validated['zone_id']);
        if (! $zone->is_active) {
            return response()->json(['message' => 'Selected zone is not available for delivery'], 422);
        }

        $address->update($validated);

        return response()->json([
            'message' => 'Address updated successfully',
            'address' => $address->load('zone.city'),
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

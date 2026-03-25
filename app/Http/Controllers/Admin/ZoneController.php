<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = Zone::with('city.state')->orderBy('sort_order');

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'city_id' => 'required|exists:cities,id',
            'name' => 'required|string|max:150',
            'is_active' => 'boolean',
            'delivery_fee' => 'numeric|min:0',
            'sort_order' => 'integer',
        ]);

        $zone = Zone::create($validated);

        return response()->json($zone, 201);
    }

    public function update(Request $request, Zone $zone)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'is_active' => 'boolean',
            'delivery_fee' => 'numeric|min:0',
            'sort_order' => 'integer',
        ]);

        $zone->update($validated);

        return response()->json($zone);
    }

    public function destroy(Request $request, Zone $zone)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $zone->delete();

        return response()->json(['message' => 'Zone deleted successfully']);
    }

    public function toggle(Request $request, Zone $zone)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $zone->update(['is_active' => !$zone->is_active]);

        return response()->json([
            'message' => 'Zone ' . ($zone->is_active ? 'activated' : 'deactivated'),
            'zone' => $zone
        ]);
    }
}

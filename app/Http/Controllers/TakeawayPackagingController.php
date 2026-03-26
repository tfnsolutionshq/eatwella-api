<?php

namespace App\Http\Controllers;

use App\Models\TakeawayPackaging;
use Illuminate\Http\Request;

class TakeawayPackagingController extends Controller
{
    public function index(Request $request)
    {
        $query = TakeawayPackaging::query();
        
        // Public/Customer request
        if (!auth('sanctum')->check() || auth('sanctum')->user()->role !== 'admin') {
            $query->where('is_active', true);
            return response()->json($query->get());
        }

        // Admin request
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'size_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $packaging = TakeawayPackaging::create($validated);

        return response()->json($packaging, 201);
    }

    public function show(Request $request, TakeawayPackaging $takeawayPackaging)
    {
        if (!auth('sanctum')->check() || auth('sanctum')->user()->role !== 'admin') {
            if (!$takeawayPackaging->is_active) {
                return response()->json(['message' => 'Not found'], 404);
            }
        }
        
        return response()->json($takeawayPackaging);
    }

    public function update(Request $request, TakeawayPackaging $takeawayPackaging)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'size_name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $takeawayPackaging->update($validated);

        return response()->json($takeawayPackaging);
    }

    public function destroy(Request $request, TakeawayPackaging $takeawayPackaging)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $takeawayPackaging->delete();

        return response()->json(null, 204);
    }

    public function toggle(Request $request, TakeawayPackaging $takeawayPackaging)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $takeawayPackaging->update(['is_active' => !$takeawayPackaging->is_active]);

        return response()->json($takeawayPackaging);
    }
}


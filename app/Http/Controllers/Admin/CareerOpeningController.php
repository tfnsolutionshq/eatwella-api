<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CareerOpening;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CareerOpeningController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $openings = CareerOpening::latest()->paginate($request->get('per_page', 15));

        return response()->json($openings);
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'employment_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'closes_at' => 'nullable|date',
            'image' => 'nullable|image|max:2048', // Max 2MB
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('career-images', 'public');
        }

        $opening = CareerOpening::create($validated);

        return response()->json([
            'message' => 'Career opening created successfully',
            'opening' => $opening
        ], 201);
    }

    public function update(Request $request, CareerOpening $opening)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
            'employment_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'closes_at' => 'nullable|date',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($opening->image_path) {
                Storage::disk('public')->delete($opening->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('career-images', 'public');
        }

        $opening->update($validated);

        return response()->json([
            'message' => 'Career opening updated successfully',
            'opening' => $opening
        ]);
    }

    public function destroy(Request $request, CareerOpening $opening)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        if ($opening->image_path) {
            Storage::disk('public')->delete($opening->image_path);
        }

        $opening->delete();

        return response()->json([
            'message' => 'Career opening deleted successfully'
        ]);
    }
}

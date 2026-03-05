<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = Tax::with('categories');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    /**
     * Publicly list active taxes.
     */
    public function list(Request $request)
    {
        $query = Tax::where('is_active', true)->with('categories');

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'rate' => 'required|numeric|min:0|max:100',
            'priority' => 'required|integer|min:0',
            'is_inclusive' => 'required|boolean',
            'branches' => 'nullable|array',
            'branches.*' => 'string', // Assuming branch names or IDs are strings
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'is_active' => 'boolean'
        ]);

        $tax = Tax::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'rate' => $validated['rate'],
            'priority' => $validated['priority'],
            'is_inclusive' => $validated['is_inclusive'],
            'branches' => $validated['branches'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['category_ids'])) {
            $tax->categories()->attach($validated['category_ids']);
        }

        return response()->json($tax->load('categories'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Tax $tax)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        return $tax->load('categories');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tax $tax)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:50',
            'description' => 'nullable|string',
            'rate' => 'sometimes|required|numeric|min:0|max:100',
            'priority' => 'sometimes|required|integer|min:0',
            'is_inclusive' => 'sometimes|required|boolean',
            'branches' => 'nullable|array',
            'branches.*' => 'string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'is_active' => 'boolean'
        ]);

        $tax->update([
            'name' => $validated['name'] ?? $tax->name,
            'type' => $validated['type'] ?? $tax->type,
            'description' => $validated['description'] ?? $tax->description,
            'rate' => $validated['rate'] ?? $tax->rate,
            'priority' => $validated['priority'] ?? $tax->priority,
            'is_inclusive' => $validated['is_inclusive'] ?? $tax->is_inclusive,
            'branches' => $validated['branches'] ?? $tax->branches,
            'is_active' => $validated['is_active'] ?? $tax->is_active,
        ]);

        if (isset($validated['category_ids'])) {
            $tax->categories()->sync($validated['category_ids']);
        }

        return response()->json($tax->load('categories'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Tax $tax)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $tax->delete();

        return response()->json(['message' => 'Tax deleted successfully']);
    }

    /**
     * Toggle the active status of the tax.
     */
    public function toggleStatus(Request $request, Tax $tax)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $tax->is_active = !$tax->is_active;
        $tax->save();

        return response()->json([
            'message' => 'Tax status updated successfully',
            'tax' => $tax
        ]);
    }
}

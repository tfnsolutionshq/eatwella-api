<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use Illuminate\Http\Request;

class DiningTableController extends Controller
{
    public function index(Request $request)
    {
        if ($request->is('api/admin/*')) {
            if ($response = $this->requireRole($request, ['admin'])) {
                return $response;
            }
            return DiningTable::orderBy('name')->paginate($request->get('per_page', 15));
        }
        
        // Public API: only return active tables
        return DiningTable::where('is_active', true)->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:dining_tables,name',
            'is_active' => 'boolean'
        ]);

        $table = DiningTable::create($validated);
        return response()->json($table, 201);
    }

    public function show(Request $request, DiningTable $diningTable)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        return $diningTable;
    }

    public function update(Request $request, DiningTable $diningTable)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'string|max:255|unique:dining_tables,name,' . $diningTable->id,
            'is_active' => 'boolean'
        ]);

        $diningTable->update($validated);
        return response()->json($diningTable);
    }

    public function destroy(Request $request, DiningTable $diningTable)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $diningTable->delete();
        return response()->json(['message' => 'Dining table deleted']);
    }

    public function toggle(Request $request, DiningTable $diningTable)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $diningTable->update(['is_active' => !$diningTable->is_active]);
        return response()->json([
            'message' => 'Dining table status toggled',
            'table' => $diningTable
        ]);
    }
}

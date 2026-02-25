<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->is('api/admin/*')) {
            if ($response = $this->requireRole($request, ['admin'])) {
                return $response;
            }
        }
        $query = Category::query();
        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }
        return $query->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $category = Category::create($validated);
        return response()->json($category, 201);
    }

    public function show(Request $request, Category $category)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        return $category;
    }

    public function update(Request $request, Category $category)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $category->update($validated);
        return response()->json($category);
    }

    public function destroy(Request $request, Category $category)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $category->delete();
        return response()->json(['message' => 'Category deleted']);
    }
}

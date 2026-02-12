<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $query = Menu::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return $query->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'images' => 'required|array|min:1', // Compulsory, at least one
            'images.*' => 'image|max:2048',
            'is_available' => 'boolean'
        ]);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('menus', 'public');
                $imagePaths[] = $path;
            }
        }
        $validated['images'] = $imagePaths;

        $menu = Menu::create($validated);
        return response()->json($menu, 201);
    }

    public function show(Menu $menu)
    {
        return $menu->load('category');
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'category_id' => 'exists:categories,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'images' => 'array|min:1', // If provided, must be at least one
            'images.*' => 'image|max:2048',
            'is_available' => 'boolean'
        ]);

        if ($request->hasFile('images')) {
            // Option 1: Replace all images
            // Option 2: Append. Let's assume Replace for PUT, or we can have a flag.
            // For simplicity in a standard REST update, we often replace the field.

            // Delete old images
            $oldImages = json_decode($menu->getRawOriginal('images'), true) ?? [];
            foreach ($oldImages as $oldImage) {
                 Storage::disk('public')->delete($oldImage);
            }

            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('menus', 'public');
                $imagePaths[] = $path;
            }
            $validated['images'] = $imagePaths;
        }

        $menu->update($validated);
        return response()->json($menu);
    }

    public function destroy(Menu $menu)
    {
        $images = json_decode($menu->getRawOriginal('images'), true) ?? [];
        foreach ($images as $image) {
            Storage::disk('public')->delete($image);
        }

        $menu->delete();
        return response()->json(['message' => 'Menu deleted']);
    }
}

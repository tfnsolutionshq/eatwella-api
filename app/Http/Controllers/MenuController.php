<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $query = Menu::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return $query->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'images' => 'required|array|min:1',
            'images.*' => 'image|max:2048',
            'is_available' => 'boolean',
            'requires_takeaway' => 'boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'complements' => 'nullable|array',
            'complements.*' => 'uuid|exists:menus,id',
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

        if ($request->has('complements')) {
            $syncData = [];
            foreach ($request->complements as $index => $complementId) {
                if ($complementId !== $menu->id) {
                    $syncData[$complementId] = ['sort_order' => $index];
                }
            }
            $menu->complements()->sync($syncData);
        }

        return response()->json($menu->load('complements'), 201);
    }

    public function show(Request $request, Menu $menu)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        return $menu->load(['category', 'complements']);
    }

    public function update(Request $request, Menu $menu)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $validated = $request->validate([
            'category_id' => 'exists:categories,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'images' => 'array|min:1',
            'images.*' => 'image|max:2048',
            'is_available' => 'boolean',
            'requires_takeaway' => 'boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'complements' => 'nullable|array',
            'complements.*' => 'uuid|exists:menus,id',
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

        if ($request->has('complements')) {
            $syncData = [];
            foreach ($request->complements as $index => $complementId) {
                if ($complementId !== $menu->id) {
                    $syncData[$complementId] = ['sort_order' => $index];
                }
            }
            $menu->complements()->sync($syncData);
        }

        return response()->json($menu->load('complements'));
    }

    public function updateStock(Request $request, Menu $menu)
    {
        if ($response = $this->requireRole($request, ['admin', 'supervisor'])) {
            return $response;
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'note'     => 'nullable|string|max:500',
        ]);

        $before = $menu->stock_quantity;
        $after  = $before + $validated['quantity'];

        $menu->stock_quantity = $after;
        if ($after > 0 && ! $menu->is_available) {
            $menu->is_available = true;
        }
        $menu->save();

        \App\Models\InventoryLog::create([
            'menu_id'          => $menu->id,
            'user_id'          => $request->user()->id,
            'type'             => 'restock',
            'quantity_before'  => $before,
            'quantity_changed' => $validated['quantity'],
            'quantity_after'   => $after,
            'note'             => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message'          => 'Stock updated successfully',
            'menu_id'          => $menu->id,
            'quantity_before'  => $before,
            'quantity_added'   => $validated['quantity'],
            'quantity_after'   => $after,
        ]);
    }

    public function inventoryLogs(Request $request, Menu $menu)
    {
        if ($response = $this->requireRole($request, ['admin', 'supervisor'])) {
            return $response;
        }

        $logs = $menu->inventoryLogs()
            ->with('user:id,name,role')
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json($logs);
    }

    public function destroy(Request $request, Menu $menu)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $hasConfirmedOrders = $menu->orderItems()
            ->whereHas('order', function ($query) {
                $query->where('status', 'confirmed');
            })
            ->exists();

        if ($hasConfirmedOrders) {
            return response()->json([
                'message' => 'Cannot delete menu. There are existing confirmed orders with this item.'
            ], 422);
        }

        $images = json_decode($menu->getRawOriginal('images'), true) ?? [];
        foreach ($images as $image) {
            Storage::disk('public')->delete($image);
        }

        $menu->delete();
        return response()->json(['message' => 'Menu deleted']);
    }
}

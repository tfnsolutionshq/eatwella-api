<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\InventoryLog;
use App\Models\Menu;
use App\Models\OutletMenuStock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * PATCH /supervisor/menus/{menu}/stock
     * PATCH /store-keeper/menus/{menu}/stock
     * Update stock for a menu item scoped to the user's outlet.
     */
    public function update(Request $request, Menu $menu)
    {
        if ($response = $this->requireRole($request, ['supervisor', 'store_keeper'])) {
            return $response;
        }

        $outlet = $this->outletContext($request);

        if (! $outlet) {
            return response()->json(['message' => 'You are not assigned to any outlet.'], 403);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'note'     => 'nullable|string|max:500',
        ]);

        $stock = OutletMenuStock::firstOrCreate(
            ['outlet_id' => $outlet->id, 'menu_id' => $menu->id],
            ['stock_quantity' => 0]
        );

        $before = $stock->stock_quantity;
        $after  = $before + $validated['quantity'];

        $stock->update(['stock_quantity' => $after]);

        InventoryLog::create([
            'menu_id'          => $menu->id,
            'user_id'          => $request->user()->id,
            'type'             => 'restock',
            'quantity_before'  => $before,
            'quantity_changed' => $validated['quantity'],
            'quantity_after'   => $after,
            'note'             => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message'         => 'Stock updated successfully.',
            'outlet_id'       => $outlet->id,
            'menu_id'         => $menu->id,
            'quantity_before' => $before,
            'quantity_added'  => $validated['quantity'],
            'quantity_after'  => $after,
        ]);
    }
}

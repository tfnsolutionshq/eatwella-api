<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryLog;
use App\Models\Menu;
use App\Models\Outlet;
use App\Models\OutletMenuStock;
use Illuminate\Http\Request;

class OutletMenuStockController extends Controller
{
    /**
     * GET /admin/outlets/{outlet}/stock
     * List all menu stock records for a given outlet.
     */
    public function index(Request $request, Outlet $outlet)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $stock = OutletMenuStock::with('menu:id,name,is_available,stock_quantity')
            ->where('outlet_id', $outlet->id)
            ->paginate($request->get('per_page', 50));

        return response()->json($stock);
    }

    /**
     * PATCH /admin/outlets/{outlet}/menus/{menu}/stock
     * Admin updates stock for a specific menu at a specific outlet.
     */
    public function update(Request $request, Outlet $outlet, Menu $menu)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
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

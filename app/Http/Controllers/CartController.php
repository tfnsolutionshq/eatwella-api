<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Menu;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Get the current session ID.
     */
    protected function getSessionId(Request $request)
    {
        // For API (stateful or stateless), if we want to rely on Laravel session:
        // Ensure 'EnsureFrontendRequestsAreStateful' or 'StartSession' middleware is active.
        // Or simply use the session ID from the request if available, or a custom header if stateless.
        
        // However, for pure API without cookies, we might still need a client-generated ID
        // OR we return a session ID on first response and expect client to send it back.
        
        // But the user requested: "Use $request->session()->getId()"
        // This implies the environment supports sessions (e.g. web middleware group or similar).
        // If this is an API route, sessions might not be started by default in Laravel 11/12 API group.
        // We will assume sessions are enabled or we enable them.
        
        // Let's use the session() helper. If it's null, we fallback or throw.
        return $request->session()->getId();
    }

    /**
     * Get the current cart content.
     */
    public function index(Request $request)
    {
        $sessionId = $this->getSessionId($request);
        $cart = Cart::with('items.menu')->where('session_id', $sessionId)->first();

        if (!$cart) {
            return response()->json(['items' => []]);
        }

        return response()->json($cart);
    }

    /**
     * Add item to cart.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $menu = Menu::findOrFail($validated['menu_id']);
        if (!$menu->is_available) {
            return response()->json(['message' => 'Menu unavailable'], 422);
        }

        $sessionId = $this->getSessionId($request);
        $cart = Cart::firstOrCreate(['session_id' => $sessionId]);

        $cartItem = $cart->items()->where('menu_id', $validated['menu_id'])->first();

        if ($cartItem) {
            $cartItem->quantity += $validated['quantity'];
            $cartItem->save();
        } else {
            $cartItem = $cart->items()->create([
                'menu_id' => $validated['menu_id'],
                'quantity' => $validated['quantity'],
            ]);
        }

        return response()->json($cart->load('items.menu'), 201);
    }

    /**
     * Update item quantity.
     */
    public function update(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $sessionId = $this->getSessionId($request);
        $cart = Cart::where('session_id', $sessionId)->firstOrFail();
        $item = $cart->items()->where('id', $itemId)->firstOrFail();

        $item->update(['quantity' => $validated['quantity']]);

        return response()->json($cart->load('items.menu'));
    }

    /**
     * Remove item from cart.
     */
    public function destroy(Request $request, $itemId)
    {
        $sessionId = $this->getSessionId($request);
        $cart = Cart::where('session_id', $sessionId)->firstOrFail();
        $item = $cart->items()->where('id', $itemId)->firstOrFail();

        $item->delete();

        return response()->json($cart->load('items.menu'));
    }
}

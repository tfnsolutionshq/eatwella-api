<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Menu;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Get the cart ID from X-Cart-ID header.
     */
    protected function getCartId(Request $request)
    {
        // If user is authenticated, use user_id, otherwise use X-Cart-ID header
        if ($request->user()) {
            return null; // Will use user_id instead
        }
        return $request->header('X-Cart-ID');
    }

    /**
     * Get the current cart content.
     */
    public function index(Request $request)
    {
        if ($request->user()) {
            $cart = Cart::with('items.menu')->where('user_id', $request->user()->id)->first();
        } else {
            $cartId = $this->getCartId($request);
            if (!$cartId) {
                return response()->json(['items' => []]);
            }
            $cart = Cart::with('items.menu')->where('session_id', $cartId)->first();
        }

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

        // Check if user is authenticated via Bearer token
        $user = auth('sanctum')->user();
        
        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        } else {
            $cartId = $request->header('X-Cart-ID');
            if (!$cartId) {
                return response()->json(['message' => 'X-Cart-ID header required'], 400);
            }
            $cart = Cart::firstOrCreate(['session_id' => $cartId]);
        }

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

        if ($request->user()) {
            $cart = Cart::where('user_id', $request->user()->id)->first();
        } else {
            $cartId = $this->getCartId($request);
            if (!$cartId) {
                return response()->json(['message' => 'X-Cart-ID header required'], 400);
            }
            $cart = Cart::where('session_id', $cartId)->first();
        }
        
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }
        
        $item = $cart->items()->where('id', $itemId)->first();
        
        if (!$item) {
            return response()->json(['message' => 'Item not found in cart'], 404);
        }

        $item->update(['quantity' => $validated['quantity']]);

        return response()->json($cart->load('items.menu'));
    }

    /**
     * Remove item from cart.
     */
    public function destroy(Request $request, $itemId)
    {
        if ($request->user()) {
            $cart = Cart::where('user_id', $request->user()->id)->first();
        } else {
            $cartId = $this->getCartId($request);
            if (!$cartId) {
                return response()->json(['message' => 'X-Cart-ID header required'], 400);
            }
            $cart = Cart::where('session_id', $cartId)->first();
        }
        
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }
        
        $item = $cart->items()->where('id', $itemId)->first();
        
        if (!$item) {
            return response()->json(['message' => 'Item not found in cart'], 404);
        }

        $item->delete();

        return response()->json($cart->load('items.menu'));
    }


    /**
     * Apply discount code to cart.
     */
    public function applyDiscount(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        if ($request->user()) {
            $cart = Cart::where('user_id', $request->user()->id)->first();
        } else {
            $cartId = $this->getCartId($request);
            if (!$cartId) {
                return response()->json(['message' => 'X-Cart-ID header required'], 400);
            }
            $cart = Cart::where('session_id', $cartId)->first();
        }
        
        if (!$cart) {
            return response()->json(['message' => 'Cart is empty'], 404);
        }

        $discount = \App\Models\Discount::where('code', strtoupper($request->code))->first();

        if (!$discount || !$discount->isValid()) {
            return response()->json(['message' => 'Invalid or expired discount code'], 400);
        }

        $cart->discount_code = $discount->code;
        $cart->save();

        return response()->json([
            'message' => 'Discount applied successfully',
            'cart' => $cart->load('items.menu'),
            'discount' => $discount
        ]);
    }

    /**
     * Remove discount from cart.
     */
    public function removeDiscount(Request $request)
    {
        if ($request->user()) {
            $cart = Cart::where('user_id', $request->user()->id)->first();
        } else {
            $cartId = $this->getCartId($request);
            if (!$cartId) {
                return response()->json(['message' => 'X-Cart-ID header required'], 400);
            }
            $cart = Cart::where('session_id', $cartId)->first();
        }
        
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $cart->discount_code = null;
        $cart->save();

        return response()->json([
            'message' => 'Discount removed',
            'cart' => $cart->load('items.menu')
        ]);
    }

}

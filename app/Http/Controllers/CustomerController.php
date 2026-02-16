<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlaced;
use App\Interfaces\PaymentGatewayInterface;

use App\Models\Cart;

class CustomerController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $paymentGateway)
    {
    }

    public function listMenus(Request $request)
    {
        $query = Menu::where('is_available', true)->with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return $query->paginate($request->get('per_page', 15));
    }

    public function showMenu(Menu $menu)
    {
        if (!$menu->is_available) {
            return response()->json(['message' => 'Menu unavailable'], 404);
        }
        return $menu->load('category');
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'order_type' => 'required|in:dine,pickup,delivery',
            'payment_type' => 'required|in:cash,gateway',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required_if:order_type,delivery|nullable|string',
            'table_number' => 'required_if:order_type,dine|nullable|string',
            'delivery_address' => 'required_if:order_type,delivery|nullable|string',
            'delivery_city' => 'required_if:order_type,delivery|nullable|string',
            'delivery_zip' => 'required_if:order_type,delivery|nullable|string',
            'items' => 'nullable|array',
            'items.*.menu_id' => 'required_with:items|exists:menus,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Get items from cart or direct
            $itemsToProcess = [];
            $cartId = $request->header('X-Cart-ID');
            $cart = $cartId ? Cart::where('session_id', $cartId)->with('items')->first() : null;

            if ($cart && $cart->items->isNotEmpty()) {
                foreach ($cart->items as $cartItem) {
                    $itemsToProcess[] = [
                        'menu_id' => $cartItem->menu_id,
                        'quantity' => $cartItem->quantity
                    ];
                }
            } elseif (!empty($validated['items'])) {
                $itemsToProcess = $validated['items'];
            }

            if (empty($itemsToProcess)) {
                throw new \Exception('No items to checkout.');
            }

            // Calculate Total and validate availability
            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($itemsToProcess as $item) {
                $menu = Menu::findOrFail($item['menu_id']);
                if (!$menu->is_available) {
                    throw new \Exception("Menu {$menu->name} is unavailable.");
                }

                $subtotal = $menu->price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'menu_id' => $menu->id,
                    'quantity' => $item['quantity'],
                    'price' => $menu->price,
                    'subtotal' => $subtotal,
                ];
            }

            // Apply Discount from Cart
            $discountAmount = 0;
            $discountCode = null;

            if ($cart && $cart->discount_code) {
                $discount = \App\Models\Discount::where('code', $cart->discount_code)->first();

                if ($discount && $discount->isValid()) {
                    $discountAmount = $discount->calculateDiscount($totalAmount);
                    $discountCode = $discount->code;

                    // Increment usage count
                    $discount->increment('used_count');
                }
            }

            $finalAmount = $totalAmount - $discountAmount;

            // Handle payment based on payment type
            if ($validated['payment_type'] === 'gateway') {
                // Initialize Payment with Paystack
                $paymentResult = $this->paymentGateway->charge(
                    $finalAmount,
                    $validated['customer_email'],
                    ['callback_url' => 'https://eatwella.ng/api/payment/callback']
                );

                if ($paymentResult['status'] === 'failed') {
                    throw new \Exception('Payment initialization failed: ' . ($paymentResult['message'] ?? 'Unknown error'));
                }

                $orderNumber = $paymentResult['reference'];
                $orderStatus = 'pending';
                $paymentStatus = 'unpaid';
                $paymentMethod = 'paystack';
            } else {
                // Cash payment
                $orderNumber = 'ORD-' . strtoupper(Str::random(10));
                $orderStatus = 'confirmed';
                $paymentStatus = 'pending';
                $paymentMethod = 'cash';
            }

            // Create Order
            $expiresAt = null;
            if ($validated['payment_type'] === 'cash' && in_array($validated['order_type'], ['dine', 'pickup'])) {
                $expiresAt = now()->addMinutes(45);
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'order_type' => $validated['order_type'],
                'payment_type' => $validated['payment_type'],
                'customer_email' => $validated['customer_email'],
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'table_number' => $validated['table_number'] ?? null,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'delivery_city' => $validated['delivery_city'] ?? null,
                'delivery_zip' => $validated['delivery_zip'] ?? null,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'discount_code' => $discountCode,
                'final_amount' => $finalAmount,
                'status' => $orderStatus,
                'expires_at' => $expiresAt
            ]);

            // Create Order Items
            foreach ($orderItemsData as $data) {
                $order->orderItems()->create($data);
            }

            // Create Invoice
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => 'INV-' . strtoupper(Str::random(10)),
                'amount' => $finalAmount,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod
            ]);

            // Clear Cart
            if (isset($cart) && $cart) {
                $cart->delete();
            }

            // Prepare response
            $response = [
                'message' => $validated['payment_type'] === 'cash'
                    ? 'Order placed successfully'
                    : 'Order created, proceed to payment',
                'order' => $order->load('orderItems', 'invoice')
            ];

            if ($validated['payment_type'] === 'gateway') {
                $response['payment'] = [
                    'authorization_url' => $paymentResult['authorization_url'],
                    'reference' => $paymentResult['reference']
                ];
            }

            // Send email after response data is ready (non-blocking)
            if ($validated['payment_type'] === 'cash') {
                try {
                    Mail::to($order->customer_email)->send(new OrderPlaced($order));
                } catch (\Exception $e) {
                    \Log::error('Failed to send order email: ' . $e->getMessage());
                }
            }

            return response()->json($response, 201);
        });
    }

    public function trackOrder($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->with(['orderItems.menu', 'invoice'])->firstOrFail();
        return $order;
    }
}

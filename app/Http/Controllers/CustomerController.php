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
            'customer_email' => 'required|email',
            'items' => 'nullable|array',
            'items.*.menu_id' => 'required_with:items|exists:menus,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Get items from cart or direct
            $itemsToProcess = [];
            $sessionId = $request->session()->getId();
            $cart = Cart::where('session_id', $sessionId)->with('items')->first();

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

            // Apply Discount
            $discountAmount = 0;
            $activeDiscount = Discount::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                })
                ->orderBy('value', 'desc')
                ->first();

            if ($activeDiscount) {
                if ($activeDiscount->type === 'percentage') {
                    $discountAmount = $totalAmount * ($activeDiscount->value / 100);
                } else {
                    $discountAmount = min($activeDiscount->value, $totalAmount);
                }
            }

            $finalAmount = $totalAmount - $discountAmount;

            // Initialize Payment with Paystack
            $paymentResult = $this->paymentGateway->charge(
                $finalAmount,
                $validated['customer_email'],
                ['callback_url' => config('app.url') . '/api/payment/callback']
            );

            if ($paymentResult['status'] === 'failed') {
                throw new \Exception('Payment initialization failed: ' . ($paymentResult['message'] ?? 'Unknown error'));
            }

            // Create Order with PENDING status
            $order = Order::create([
                'order_number' => $paymentResult['reference'],
                'customer_email' => $validated['customer_email'],
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'status' => 'pending' // Admin won't see it yet
            ]);

            // Create Order Items
            foreach ($orderItemsData as $data) {
                $order->orderItems()->create($data);
            }

            // Create Invoice with UNPAID status
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => 'INV-' . strtoupper(Str::random(10)),
                'amount' => $finalAmount,
                'payment_status' => 'unpaid',
                'payment_method' => 'paystack'
            ]);

            // Clear Cart
            if (isset($cart) && $cart) {
                $cart->delete();
            }

            return response()->json([
                'message' => 'Order created, proceed to payment',
                'order' => $order->load('orderItems', 'invoice'),
                'payment' => [
                    'authorization_url' => $paymentResult['authorization_url'],
                    'reference' => $paymentResult['reference']
                ]
            ], 201);
        });
    }

    public function trackOrder($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->with(['orderItems.menu', 'invoice'])->firstOrFail();
        return $order;
    }
}

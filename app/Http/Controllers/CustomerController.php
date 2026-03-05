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
        $user = auth('sanctum')->user();
        if ($user && !in_array($user->role, ['customer', 'cashier'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $isCashier = $user && $user->role === 'cashier';
        $isCustomer = $user && $user->role === 'customer';

        $rules = [
            'order_type' => 'required|in:dine,pickup,delivery',
            'payment_type' => 'required|in:cash,gateway,loyalty_points',
            'items' => 'nullable|array',
            'items.*.menu_id' => 'required_with:items|exists:menus,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ];

        // Guest users must provide customer details
        if (!$user || $isCashier) {
            $rules['customer_name'] = 'required|string|max:255';
            $rules['customer_email'] = 'required|email';
            $rules['customer_phone'] = 'required_if:order_type,delivery|nullable|string';
        }

        // Table number for dine-in
        if ($request->order_type === 'dine') {
            $rules['table_number'] = 'required|string';
        }

        // For delivery orders
        if ($request->order_type === 'delivery') {
            if ($isCustomer) {
                // Logged in: can use address_id OR provide address
                $rules['address_id'] = 'nullable|exists:addresses,id';
                $rules['delivery_address'] = 'required_without:address_id|nullable|string';
                $rules['delivery_city'] = 'required_without:address_id|nullable|string';
                $rules['delivery_zip'] = 'required_without:address_id|nullable|string';
            } else {
                // Guest: must provide address
                $rules['delivery_address'] = 'required|string';
                $rules['delivery_city'] = 'required|string';
                $rules['delivery_zip'] = 'required|string';
            }
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $request, $user, $isCashier, $isCustomer) {
            // Get items from cart or direct
            $itemsToProcess = [];
            $cart = null;

            if ($user) {
                $cart = Cart::where('user_id', $user->id)->with('items')->first();
            } else {
                $cartId = $request->header('X-Cart-ID');
                $cart = $cartId ? Cart::where('session_id', $cartId)->with('items')->first() : null;
            }

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
            
            // Tax Calculation Variables
            $activeTaxes = \App\Models\Tax::where('is_active', true)->with('categories')->get();
            $totalTaxAmount = 0;
            $totalExclusiveTax = 0;
            $taxDetails = [];

            foreach ($itemsToProcess as $item) {
                $menu = Menu::findOrFail($item['menu_id']);
                if (!$menu->is_available) {
                    throw new \Exception("Menu {$menu->name} is unavailable.");
                }

                $subtotal = $menu->price * $item['quantity'];
                $totalAmount += $subtotal;
                
                // Calculate Taxes (Pre-discount)
                $itemTaxes = $activeTaxes->filter(function ($tax) use ($menu) {
                    return $tax->categories->contains('id', $menu->category_id);
                });

                foreach ($itemTaxes as $tax) {
                    $taxValue = 0;
                    if ($tax->is_inclusive) {
                        $taxValue = $subtotal - ($subtotal / (1 + ($tax->rate / 100)));
                    } else {
                        $taxValue = $subtotal * ($tax->rate / 100);
                        $totalExclusiveTax += $taxValue;
                    }

                    $totalTaxAmount += $taxValue;
                    
                    if (!isset($taxDetails[$tax->name])) {
                        $taxDetails[$tax->name] = [
                            'rate' => (float)$tax->rate,
                            'type' => $tax->type,
                            'amount' => 0
                        ];
                    }
                    $taxDetails[$tax->name]['amount'] += $taxValue;
                }

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

            // Adjust Tax for Discount
            if ($discountAmount > 0 && $totalAmount > 0) {
                $discountRatio = $discountAmount / $totalAmount;
                
                $totalTaxAmount -= ($totalTaxAmount * $discountRatio);
                $totalExclusiveTax -= ($totalExclusiveTax * $discountRatio);
                
                foreach ($taxDetails as &$detail) {
                    $detail['amount'] -= ($detail['amount'] * $discountRatio);
                    $detail['amount'] = round($detail['amount'], 2);
                }
            }
            
            $totalTaxAmount = round($totalTaxAmount, 2);
            $totalExclusiveTax = round($totalExclusiveTax, 2);

            $finalAmount = $totalAmount - $discountAmount + $totalExclusiveTax;

            // Get customer details
            $customerName = $isCustomer ? $user->name : $validated['customer_name'];
            $customerEmail = $isCustomer ? $user->email : $validated['customer_email'];
            $customerPhone = $isCustomer ? $user->phone : ($validated['customer_phone'] ?? null);
            $orderUserId = $isCustomer ? $user->id : null;
            $cashierId = $isCashier ? $user->id : null;

            // Handle payment based on payment type
            if ($validated['payment_type'] === 'gateway') {
                // Initialize Payment with Paystack
                $paymentResult = $this->paymentGateway->charge(
                    $finalAmount,
                    $customerEmail,
                    ['callback_url' => 'https://eatwella.ng/api/payment/callback']
                );

                if ($paymentResult['status'] === 'failed') {
                    throw new \Exception('Payment initialization failed: ' . ($paymentResult['message'] ?? 'Unknown error'));
                }

                $orderNumber = $paymentResult['reference'];
                $orderStatus = 'pending';
                $paymentStatus = 'unpaid';
                $paymentMethod = 'paystack';
            } elseif ($validated['payment_type'] === 'loyalty_points') {
                if (!$user) {
                     throw new \Exception('You must be logged in to pay with loyalty points.');
                }

                $minPoints = (int) (\App\Models\Setting::where('key', 'loyalty_min_points_redemption')->value('value') ?? 100);
                $conversionRate = (float) (\App\Models\Setting::where('key', 'loyalty_conversion_rate')->value('value') ?? 1.0);

                if ($user->loyalty_points < $minPoints) {
                     throw new \Exception("You need a minimum of {$minPoints} loyalty points to redeem.");
                }

                $pointsNeeded = ceil($finalAmount / $conversionRate);

                if ($user->loyalty_points < $pointsNeeded) {
                     throw new \Exception("Insufficient loyalty points. You need {$pointsNeeded} points for this order.");
                }

                $user->decrement('loyalty_points', $pointsNeeded);

                $orderNumber = 'ORD-' . strtoupper(Str::random(10));
                $orderStatus = 'confirmed';
                $paymentStatus = 'paid';
                $paymentMethod = 'loyalty_points';
            } else {
                // Cash payment
                $orderNumber = 'ORD-' . strtoupper(Str::random(10));
                $orderStatus = 'confirmed';
                $paymentStatus = 'pending';
                $paymentMethod = 'cash';
            }

            // Handle delivery address
            $deliveryAddress = null;
            $deliveryCity = null;
            $deliveryZip = null;

            if ($validated['order_type'] === 'delivery') {
                if ($isCustomer && !empty($validated['address_id'])) {
                    // Use saved address
                    $address = \App\Models\Address::where('id', $validated['address_id'])
                        ->where('user_id', $user->id)
                        ->firstOrFail();
                    $deliveryAddress = $address->street_address;
                    $deliveryCity = $address->state;
                    $deliveryZip = $address->postal_code;
                } else {
                    // Use provided address
                    $deliveryAddress = $validated['delivery_address'] ?? null;
                    $deliveryCity = $validated['delivery_city'] ?? null;
                    $deliveryZip = $validated['delivery_zip'] ?? null;
                }
            }

            // Create Order
            $expiresAt = null;
            if ($validated['payment_type'] === 'cash' && in_array($validated['order_type'], ['dine', 'pickup'])) {
                $expiresAt = now()->addMinutes(45);
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $orderUserId,
                'cashier_id' => $cashierId,
                'order_type' => $validated['order_type'],
                'payment_type' => $validated['payment_type'],
                'customer_email' => $customerEmail,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'table_number' => $validated['table_number'] ?? null,
                'delivery_address' => $deliveryAddress,
                'delivery_city' => $deliveryCity,
                'delivery_zip' => $deliveryZip,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $totalTaxAmount,
                'tax_details' => $taxDetails,
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
                'message' => in_array($validated['payment_type'], ['cash', 'loyalty_points'])
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
            if (in_array($validated['payment_type'], ['cash', 'loyalty_points'])) {
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

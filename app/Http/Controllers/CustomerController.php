<?php

namespace App\Http\Controllers;

use App\Interfaces\PaymentGatewayInterface;
use App\Mail\OrderPlaced;
use App\Models\Cart;
use App\Models\Discount;
use App\Models\Invoice;
use App\Models\Menu;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $paymentGateway) {}

    public function takeawayPrice()
    {
        $value = \App\Models\Setting::where('key', 'takeaway_price')->value('value');
        $price = (float) ($value ?? 0);
        if ($price < 0) $price = 0;
        return response()->json(['takeaway_price' => round($price, 2)]);
    }

    private function checkAvailabilityHours(): void
    {
        $hoursJson = \App\Models\Setting::where('key', 'availability_hours')->value('value');
        if (!$hoursJson) return;

        $hours = json_decode($hoursJson, true);
        if (empty($hours)) return;

        $tz  = \App\Models\Setting::where('key', 'restaurant_timezone')->value('value') ?? 'Africa/Lagos';
        $now = \Carbon\Carbon::now($tz);
        $day = $now->format('l');

        $entry = collect($hours)->firstWhere('day', $day);

        if (!$entry || !($entry['enabled'] ?? false)) {
            abort(403, 'Restaurant is currently closed.');
        }

        $open  = \Carbon\Carbon::createFromFormat('g:i A', $entry['open'],  $tz)->setDateFrom($now);
        $close = \Carbon\Carbon::createFromFormat('g:i A', $entry['close'], $tz)->setDateFrom($now);

        if ($now->lt($open) || $now->gt($close)) {
            abort(403, 'Restaurant is currently closed.');
        }
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
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        return $query->paginate($request->get('per_page', 15));
    }

    public function showMenu(Menu $menu)
    {
        if (! $menu->is_available) {
            return response()->json(['message' => 'Menu unavailable'], 404);
        }

        $menu->load(['category', 'complements' => function ($query) {
            $query->where('is_available', true)
                  ->where('menu_complements.is_active', true)
                  ->orderBy('menu_complements.sort_order', 'asc');
        }]);

        // If no curated complements, try to get algorithmic recommendations
        if ($menu->complements->isEmpty()) {
            $recommendations = \App\Models\MenuRecommendation::where('menu_id', $menu->id)
                ->orderByDesc('score')
                ->limit(4)
                ->get()
                ->pluck('recommended_menu_id');

            if ($recommendations->isNotEmpty()) {
                $algorithmicComplements = Menu::whereIn('id', $recommendations)
                    ->where('is_available', true)
                    ->get();
                // Set dynamic attribute to indicate they are algorithmic
                $menu->setRelation('complements', $algorithmicComplements);
            }
        }

        return response()->json($menu);
    }

    public function checkout(Request $request)
    {
        $this->checkAvailabilityHours();

        $user = auth('sanctum')->user();
        if ($user) {
            $role = strtolower(trim($user->role));
            if (! in_array($role, ['customer', 'attendant'], true)) {
                return response()->json(['message' => 'Forbidden: Only customers and attendants can checkout. (Current role: ' . $user->role . ')'], 403);
            }
            $isAttendant = $role === 'attendant';
            $isCustomer = $role === 'customer';
        } else {
            $isAttendant = false;
            $isCustomer = false;
        }

        $rules = [
            'order_type' => 'required|in:dine,pickup,delivery',
            'payment_type' => 'required|in:cash,gateway,loyalty_points',
            'items' => 'nullable|array',
            'items.*.menu_id' => 'required_with:items|exists:menus,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.packaging_id' => 'nullable|exists:takeaway_packagings,id',
        ];

        // Guest users must provide customer details
        if (! $user || $isAttendant) {
            $rules['customer_name'] = 'required|string|max:255';
            $rules['customer_email'] = 'required|email';
            $rules['customer_phone'] = 'required_if:order_type,delivery|nullable|string';
        }

        // Table number for dine-in is optional
        if ($request->order_type === 'dine') {
            $rules['table_number'] = ['nullable', 'string', \Illuminate\Validation\Rule::exists('dining_tables', 'name')->where('is_active', true)];
        }

        // For delivery orders
        if ($request->order_type === 'delivery') {
            if ($isCustomer) {
                // Logged in: can use saved address_id OR provide a one-time address + zone
                $rules['address_id']        = 'nullable|exists:addresses,id';
                $rules['delivery_zone_id']  = 'required_without:address_id|exists:zones,id';
                $rules['delivery_address']  = 'required_without:address_id|string';
            } else {
                // Guest: must provide address + zone
                $rules['delivery_zone_id'] = 'required|exists:zones,id';
                $rules['delivery_address'] = 'required|string';
            }
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $request, $user, $isAttendant, $isCustomer) {
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
                        'quantity' => $cartItem->quantity,
                        'packaging_id' => $cartItem->packaging_id,
                    ];
                }
            } elseif (! empty($validated['items'])) {
                $itemsToProcess = $validated['items'];
            }

            if (empty($itemsToProcess)) {
                throw new \Exception('No items to checkout.');
            }

            // Calculate Total and validate availability
            $totalAmount = 0;
            $orderItemsData = [];
            $takeawayAmount = 0;

            // Tax Calculation Variables
            $activeTaxes = \App\Models\Tax::where('is_active', true)->get();
            $totalTaxAmount = 0;
            $totalExclusiveTax = 0;
            $taxDetails = [];

            $needsTakeaway = in_array($validated['order_type'], ['delivery', 'pickup']);

            foreach ($itemsToProcess as $item) {
                $menu = Menu::findOrFail($item['menu_id']);
                if (! $menu->is_available) {
                    throw new \Exception("Menu {$menu->name} is unavailable.");
                }

                $subtotal = $menu->price * $item['quantity'];
                $totalAmount += $subtotal;

                $itemPackagingId = null;
                $itemPackagingPrice = 0;

                if ($needsTakeaway && $menu->requires_takeaway) {
                    if (empty($item['packaging_id'])) {
                        throw new \Exception("Please select a packaging size for {$menu->name}.");
                    }

                    $packaging = \App\Models\TakeawayPackaging::where('id', $item['packaging_id'])->where('is_active', true)->first();
                    if ($packaging) {
                        $itemPackagingId = $packaging->id;
                        $itemPackagingPrice = $packaging->price;
                        $takeawayAmount += $itemPackagingPrice * $item['quantity'];
                    } else {
                        throw new \Exception("Selected packaging for {$menu->name} is unavailable.");
                    }
                }

                $orderItemsData[] = [
                    'menu_id'  => $menu->id,
                    'quantity' => $item['quantity'],
                    'price'    => $menu->price,
                    'subtotal' => $subtotal,
                    'packaging_id' => $itemPackagingId,
                    'packaging_price' => $itemPackagingPrice,
                ];
            }
            $takeawayAmount = round($takeawayAmount, 2);

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

            // Calculate Taxes on Total Amount (Post-discount base)
            $taxableAmount = $totalAmount - $discountAmount;

            if ($taxableAmount > 0) {
                foreach ($activeTaxes as $tax) {
                    $taxValue = 0;
                    if ($tax->is_inclusive) {
                        // Inclusive tax is extracted from the amount
                        $taxValue = $taxableAmount - ($taxableAmount / (1 + ($tax->rate / 100)));
                    } else {
                        // Exclusive tax is added on top of the amount
                        $taxValue = $taxableAmount * ($tax->rate / 100);
                        $totalExclusiveTax += $taxValue;
                    }

                    $totalTaxAmount += $taxValue;

                    if (! isset($taxDetails[$tax->name])) {
                        $taxDetails[$tax->name] = [
                            'rate' => (float) $tax->rate,
                            'type' => $tax->type,
                            'amount' => 0,
                        ];
                    }
                    $taxDetails[$tax->name]['amount'] += $taxValue;
                }
            }

            // Round tax details
            foreach ($taxDetails as &$detail) {
                $detail['amount'] = round($detail['amount'], 2);
            }

            $totalTaxAmount = round($totalTaxAmount, 2);
            $totalExclusiveTax = round($totalExclusiveTax, 2);

            // Handle delivery address (must happen before zone fee lookup)
            $deliveryAddress = null;
            $deliveryCity = null;
            $deliveryZip = null;

            if ($validated['order_type'] === 'delivery') {
                if ($isCustomer && ! empty($validated['address_id'])) {
                    $address = \App\Models\Address::where('id', $validated['address_id'])
                        ->where('user_id', $user->id)
                        ->firstOrFail();
                    $validated['delivery_zone_id'] = $address->zone_id;
                    $deliveryAddress = $address->street_address;
                } else {
                    $deliveryAddress = $validated['delivery_address'] ?? null;
                }
            }

            $deliveryFee = 0;
            $deliveryZoneId = null;
            if ($validated['order_type'] === 'delivery') {
                $zone = \App\Models\Zone::findOrFail($validated['delivery_zone_id']);

                if (! $zone->is_active) {
                    throw new \Exception("Delivery is not currently available in the selected zone: {$zone->name}.");
                }

                $deliveryFee = round((float) $zone->delivery_fee, 2);
                $deliveryZoneId = $zone->id;
            }

            $finalAmount = $totalAmount - $discountAmount + $totalExclusiveTax + $deliveryFee + $takeawayAmount;

            // Get customer details
            $customerName = $isCustomer ? $user->name : $validated['customer_name'];
            $customerEmail = $isCustomer ? $user->email : $validated['customer_email'];
            $customerPhone = $isCustomer ? $user->phone : ($validated['customer_phone'] ?? null);
            $orderUserId = $isCustomer ? $user->id : null;
            $attendantId = $isAttendant ? $user->id : null;

            // Generate order number upfront for all payment types
            $orderNumber = Order::generateOrderNumber();

            // Handle payment based on payment type
            if ($validated['payment_type'] === 'gateway') {
                // Initialize Payment with Paystack using our order number as reference
                $paymentResult = $this->paymentGateway->charge(
                    $finalAmount,
                    $customerEmail,
                    ['callback_url' => 'https://eatwella.tfnsolutions.us/api/payment/callback', 'reference' => $orderNumber]
                );

                if ($paymentResult['status'] === 'failed') {
                    throw new \Exception('Payment initialization failed: '.($paymentResult['message'] ?? 'Unknown error'));
                }

                $orderStatus = 'pending';
                $paymentStatus = 'unpaid';
                $paymentMethod = 'paystack';
            } elseif ($validated['payment_type'] === 'loyalty_points') {
                if (! $user) {
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

                $orderStatus = 'confirmed';
                $paymentStatus = 'paid';
                $paymentMethod = 'loyalty_points';
            } else {
                // Cash payment
                if ($isAttendant) {
                    // Attendant placed order with cash: assumed paid/confirmed immediately
                    $orderStatus = 'confirmed';
                    $paymentStatus = 'paid';
                } else {
                    // Customer placed order with cash: pending until attendant confirms payment
                    $orderStatus = 'pending';
                    $paymentStatus = 'pending';
                }
                
                $paymentMethod = 'cash';
            }

            // Create Order
            $expiresAt = null;
            if ($validated['payment_type'] === 'cash' && in_array($validated['order_type'], ['dine', 'pickup'])) {
                $expiresAt = now()->addMinutes(45);
            }

            $deliveryPin = null;
            if ($validated['order_type'] === 'delivery') {
                $deliveryPin = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $orderUserId,
                'attendant_id' => $attendantId,
                'order_type' => $validated['order_type'],
                'payment_type' => $validated['payment_type'],
                'customer_email' => $customerEmail,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'table_number' => $validated['table_number'] ?? null,
                'delivery_address' => $deliveryAddress,
                'delivery_city' => null,
                'delivery_zip' => null,
                'delivery_zone_id' => $deliveryZoneId,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $totalTaxAmount,
                'delivery_fee'    => $deliveryFee,
                'takeaway_amount'  => $takeawayAmount,
                'tax_details'      => $taxDetails,
                'discount_code' => $discountCode,
                'final_amount' => $finalAmount,
                'status' => $orderStatus,
                'delivery_pin' => $deliveryPin,
                'expires_at' => $expiresAt,
            ]);

            // Create Order Items
            foreach ($orderItemsData as $data) {
                $order->orderItems()->create($data);
            }

            // Create Invoice
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => 'INV-'.strtoupper(Str::random(10)),
                'amount' => $finalAmount,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
            ]);

            // Clear Cart
            if (isset($cart) && $cart) {
                $cart->delete();
            }

            // Prepare response
            $order->makeVisible('delivery_pin');
            $response = [
                'message' => in_array($validated['payment_type'], ['cash', 'loyalty_points'])
                    ? 'Order placed successfully'
                    : 'Order created, proceed to payment',
                'order' => $order->load('orderItems', 'invoice', 'deliveryAgent', 'assignedBySupervisor'),
            ];

            if ($validated['payment_type'] === 'gateway') {
                $response['payment'] = [
                    'authorization_url' => $paymentResult['authorization_url'],
                    'reference' => $paymentResult['reference'],
                ];
            }

            // Send email after response data is ready (non-blocking)
            if (in_array($validated['payment_type'], ['cash', 'loyalty_points'])) {
                try {
                    Mail::to($order->customer_email)->send(new OrderPlaced($order));
                } catch (\Exception $e) {
                    \Log::error('Failed to send order email: '.$e->getMessage());
                }
            }

            return response()->json($response, 201);
        });
    }

    public function trackOrder($identifier)
    {
        $order = Order::whereRaw('UPPER(order_number) = ?', [strtoupper($identifier)])
            ->orWhere('id', $identifier)
            ->with(['orderItems.menu', 'orderItems.packaging', 'invoice', 'deliveryAgent', 'assignedBySupervisor', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name'])
            ->firstOrFail();

        $order->makeVisible('delivery_pin');

        return $order;
    }
}

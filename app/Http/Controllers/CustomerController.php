<?php

namespace App\Http\Controllers;

use App\Interfaces\PaymentGatewayInterface;
use App\Mail\OrderPlaced;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Discount;
use App\Models\Invoice;
use App\Models\Menu;
use App\Models\MenuRecommendation;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TakeawayPackaging;
use App\Models\Tax;
use App\Models\User;
use App\Models\Zone;
use App\Services\LoyaltyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(
        protected PaymentGatewayInterface $paymentGateway,
        protected LoyaltyService $loyalty,
        protected NotificationService $notifications,
    ) {}

    public function takeawayPrice()
    {
        $value = Setting::where('key', 'takeaway_price')->value('value');
        $price = (float) ($value ?? 0);
        if ($price < 0) $price = 0;
        return response()->json(['takeaway_price' => round($price, 2)]);
    }

    private function checkAvailabilityHours(string $orderType = null, ?string $outletId = null): void
    {
        // Resolve hours: outlet override first, then global fallback
        if ($outletId) {
            $overrides = \App\Models\OutletAvailabilityHour::where('outlet_id', $outletId)->get();
            if ($overrides->isNotEmpty()) {
                $hours = $overrides->map(fn ($h) => [
                    'day'         => $h->day,
                    'enabled'     => $h->enabled,
                    'open'        => $h->open,
                    'close'       => $h->close,
                    'order_types' => $h->order_types,
                ])->toArray();
                $this->assertWithinHours($hours, $orderType);
                return;
            }
        }

        $hoursJson = Setting::where('key', 'availability_hours')->value('value');
        if (! $hoursJson) return;
        $hours = json_decode($hoursJson, true);
        if (empty($hours)) return;
        $this->assertWithinHours($hours, $orderType);
    }

    private function assertWithinHours(array $hours, ?string $orderType): void
    {
        $tz  = Setting::where('key', 'restaurant_timezone')->value('value') ?? 'Africa/Lagos';
        $now = \Carbon\Carbon::now($tz);
        $day = $now->format('l');

        $entry = collect($hours)->firstWhere('day', $day);

        if (!$entry || !($entry['enabled'] ?? false) || empty($entry['open']) || empty($entry['close'])) {
            abort(403, 'Restaurant is currently closed.');
        }

        $open  = \Carbon\Carbon::parse($entry['open'],  $tz)->setDateFrom($now);
        $close = \Carbon\Carbon::parse($entry['close'], $tz)->setDateFrom($now);

        if ($now->lt($open) || $now->gt($close)) {
            abort(403, 'Restaurant is currently closed.');
        }

        if ($orderType) {
            $orderTypes = $entry['order_types'] ?? [];
            $enabled    = $orderTypes[$orderType] ?? true;
            if (! $enabled) {
                abort(403, ucfirst($orderType) . ' orders are not available today.');
            }
        }
    }

    private function getTaxMode(): string
    {
        return Setting::where('key', 'tax_mode')->value('value') ?? 'exclusive';
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

        $outletId    = $request->filled('outlet_id') ? $request->outlet_id : null;
        $paginated   = $query->paginate($request->get('per_page', 15));
        $taxMode     = $this->getTaxMode();
        $activeTaxes = Tax::where('is_active', true)->get();

        $paginated->setCollection(
            $paginated->getCollection()->map(function ($menu) use ($taxMode, $activeTaxes, $outletId) {
                $totalRate = $activeTaxes->sum(fn($t) => (float) $t->rate);
                if ($taxMode === 'inclusive' && $activeTaxes->isNotEmpty()) {
                    $menu->price = round($menu->price * (1 + $totalRate / 100), 2);
                }
                if ($outletId) {
                    $outletStock = \App\Models\OutletMenuStock::where('outlet_id', $outletId)
                        ->where('menu_id', $menu->id)
                        ->value('stock_quantity');
                    $menu->stock_quantity = $outletStock ?? 0;
                }
                return $menu;
            })
        );

        return $paginated;
    }

    public function showMenu(Request $request, Menu $menu)
    {
        if (! $menu->is_available) {
            return response()->json(['message' => 'Menu unavailable'], 404);
        }

        $menu->load(['category', 'complements' => function ($query) {
            $query->where('is_available', true)
                  ->where('menu_complements.is_active', true)
                  ->orderBy('menu_complements.sort_order', 'asc');
        }]);

        if ($menu->complements->isEmpty()) {
            $recommendations = MenuRecommendation::where('menu_id', $menu->id)
                ->orderByDesc('score')
                ->limit(4)
                ->get()
                ->pluck('recommended_menu_id');

            if ($recommendations->isNotEmpty()) {
                $algorithmicComplements = Menu::whereIn('id', $recommendations)
                    ->where('is_available', true)
                    ->get();
                $menu->setRelation('complements', $algorithmicComplements);
            }
        }

        $taxMode     = $this->getTaxMode();
        $activeTaxes = Tax::where('is_active', true)->get();

        if ($taxMode === 'inclusive' && $activeTaxes->isNotEmpty()) {
            $totalRate   = $activeTaxes->sum(fn($t) => (float) $t->rate);
            $menu->price = round($menu->price * (1 + $totalRate / 100), 2);
        }

        if ($request->filled('outlet_id')) {
            $outletStock = \App\Models\OutletMenuStock::where('outlet_id', $request->outlet_id)
                ->where('menu_id', $menu->id)
                ->value('stock_quantity');
            $menu->stock_quantity = $outletStock ?? 0;
        }

        return response()->json($menu);
    }

    public function checkout(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user) {
            $role = strtolower(trim($user->role));
            if (! in_array($role, ['customer', 'attendant', 'admin'], true)) {
                return response()->json(['message' => 'Forbidden: Only customers, attendants and admins can checkout. (Current role: ' . $user->role . ')'], 403);
            }
            $isAttendant = in_array($role, ['attendant', 'admin', 'supervisor']);
            $isCustomer = $role === 'customer';
        } else {
            $isAttendant = false;
            $isCustomer = false;
        }

        $isStaff = $user && in_array(strtolower(trim($user->role)), ['attendant', 'admin', 'supervisor'], true);

        // Resolve cart if any
        if ($user) {
            $cartForOutlet = Cart::where('user_id', $user->id)->first();
        } else {
            $cartIdHeader  = $request->header('X-Cart-ID');
            $cartForOutlet = $cartIdHeader ? Cart::where('session_id', $cartIdHeader)->first() : null;
        }

        $rules = [
            'outlet_id'     => 'nullable|uuid|exists:outlets,id',
            'order_type'    => 'required|in:dine,pickup,delivery',
            'payment_type'  => $isAttendant ? 'nullable|in:cash,pos,transfer,gateway,loyalty_points' : 'required|in:cash,pos,transfer,gateway,loyalty_points',
            'items'         => 'nullable|array',
            'items.*.menu_id'      => 'required_with:items|exists:menus,id',
            'items.*.quantity'     => 'required_with:items|integer|min:1',
            'items.*.packaging_id' => 'nullable|exists:takeaway_packagings,id',
            'customer_note' => 'nullable|string|max:500',
        ];

        // Staff can link order to an existing customer and specify post-create action
        if ($isAttendant) {
            $rules['customer_user_id']    = 'nullable|exists:users,id';
            $rules['action']              = 'nullable|in:send_to_kitchen,complete';
            $rules['payment_account_id']  = 'nullable|exists:payment_accounts,id';
        }

        // Guests must provide customer details
        if (! $user && ! $isAttendant) {
            $rules['customer_name']  = 'required|string|max:255';
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

        // Resolve outlet: validated payload > X-Outlet-ID header > cart > token ability > user outlet_id
        $outletId = $validated['outlet_id']
            ?? $request->header('X-Outlet-ID')
            ?? ($cartForOutlet?->outlet_id);

        if (! $outletId && $user) {
            $token = $user->currentAccessToken();
            if ($token) {
                $abilities = $token->abilities ?? [];
                if (is_string($abilities)) {
                    $abilities = json_decode($abilities, true) ?? [];
                }
                foreach ($abilities as $ability) {
                    if (str_starts_with((string) $ability, 'outlet:')) {
                        $outletId = substr($ability, 7);
                        break;
                    }
                }
            }
            if (! $outletId && $user->outlet_id) {
                $outletId = $user->outlet_id;
            }
        }

        if ($outletId) {
            $outlet = \App\Models\Outlet::find($outletId);
            if (! $outlet || ! $outlet->is_active) {
                return response()->json(['message' => 'The selected outlet is not available.'], 422);
            }
        }

        if (!$isStaff) {
            $this->checkAvailabilityHours($request->order_type, $outletId);
        }

        return DB::transaction(function () use ($validated, $request, $user, $isAttendant, $isCustomer, $outletId) {
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
            $activeTaxes       = Tax::where('is_active', true)->get();
            $taxMode           = $this->getTaxMode();
            $totalTaxAmount    = 0;
            $totalExclusiveTax = 0;
            $taxDetails        = [];
            $totalRate         = $activeTaxes->sum(fn($t) => (float) $t->rate);

            $needsTakeaway = in_array($validated['order_type'], ['delivery', 'pickup']);

            foreach ($itemsToProcess as $item) {
                $menu = Menu::findOrFail($item['menu_id']);
                if (! $menu->is_available) {
                    throw new \Exception("Menu {$menu->name} is unavailable.");
                }

                if ($outletId) {
                    if ($menu->stock_quantity !== null) {
                        $outletStock = \App\Models\OutletMenuStock::where('outlet_id', $outletId)
                            ->where('menu_id', $menu->id)
                            ->first();
                        $available = $outletStock ? $outletStock->stock_quantity : 0;
                        if ($available < $item['quantity']) {
                            throw new \Exception("Insufficient stock for {$menu->name} at this outlet. Only {$available} left.");
                        }
                    }
                } else {
                    if ($menu->stock_quantity !== null && $menu->stock_quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for {$menu->name}. Only {$menu->stock_quantity} left.");
                    }
                }

                // Use inclusive price (tax baked in) if mode is inclusive
                $unitPrice = ($taxMode === 'inclusive' && $activeTaxes->isNotEmpty())
                    ? round((float) $menu->price * (1 + $totalRate / 100), 2)
                    : (float) $menu->price;

                $subtotal     = $unitPrice * $item['quantity'];
                $totalAmount += $subtotal;

                $itemPackagingId = null;
                $itemPackagingPrice = 0;

                if ($needsTakeaway && $menu->requires_takeaway) {
                    if (empty($item['packaging_id'])) {
                        throw new \Exception("Please select a packaging size for {$menu->name}.");
                    }

                    $packaging = TakeawayPackaging::where('id', $item['packaging_id'])->where('is_active', true)->first();
                    if ($packaging) {
                        $itemPackagingId = $packaging->id;
                        $itemPackagingPrice = $packaging->price;
                        $takeawayAmount += $itemPackagingPrice * $item['quantity'];
                    } else {
                        throw new \Exception("Selected packaging for {$menu->name} is unavailable.");
                    }
                }

                $orderItemsData[] = [
                    'menu_id'         => $menu->id,
                    'quantity'        => $item['quantity'],
                    'price'           => (float) $menu->price, // always store raw base price
                    'subtotal'        => $subtotal,
                    'packaging_id'    => $itemPackagingId,
                    'packaging_price' => $itemPackagingPrice,
                ];
            }
            $takeawayAmount = round($takeawayAmount, 2);

            // Apply menu discount from cart code
            $discountAmount = 0;
            $discountCode = null;
            $freeDeliveryAmount = 0;
            $freeDeliveryDiscount = null;

            if ($cart && $cart->discount_code) {
                $menuDiscount = Discount::where('code', $cart->discount_code)
                    ->where('discount_type', 'menu')
                    ->first();

                if ($menuDiscount && $menuDiscount->isValid()) {
                    if ($menuDiscount->isForUser($user)) {
                        $discountAmount = $menuDiscount->calculateDiscount($totalAmount);
                        $discountCode = $menuDiscount->code;
                        $menuDiscount->increment('used_count');
                    }
                }
            }

            // Auto-apply free_delivery discount for delivery orders
            if ($validated['order_type'] === 'delivery') {
                $freeDeliveryQuery = Discount::where('discount_type', 'free_delivery')
                    ->where('is_active', true)
                    ->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->where('is_indefinite', true)
                          ->orWhere(function ($q2) {
                              $q2->where('is_indefinite', false)
                                 ->where('end_date', '>=', now());
                          });
                    })
                    ->where(function ($q) use ($user) {
                        // applies to all (no users in pivot) OR specifically to this user
                        $q->whereDoesntHave('users')
                          ->orWhereHas('users', fn($q2) => $q2->where('users.id', optional($user)->id));
                    })
                    ->where(function ($q) {
                        $q->whereNull('usage_limit')
                          ->orWhereColumn('used_count', '<', 'usage_limit');
                    })
                    ->orderByRaw('(SELECT COUNT(*) FROM discount_user WHERE discount_user.discount_id = discounts.id) = 0 ASC') // user-specific first
                    ->first();

                if ($freeDeliveryDiscount = $freeDeliveryQuery) {
                    // actual fee zeroing happens after zone fee is calculated below
                }
            }

            // Calculate Taxes on Total Amount (Post-discount base)
            $taxableAmount = $totalAmount - $discountAmount;

            if ($taxableAmount > 0) {
                foreach ($activeTaxes as $tax) {
                    if ($taxMode === 'inclusive') {
                        // Tax is already baked into the customer-facing price.
                        // Extract it back out for audit — does NOT add to final amount.
                        $taxValue = $taxableAmount - ($taxableAmount / (1 + ($tax->rate / 100)));
                    } else {
                        // Exclusive: tax is added on top.
                        $taxValue = $taxableAmount * ($tax->rate / 100);
                        $totalExclusiveTax += $taxValue;
                    }

                    $totalTaxAmount += $taxValue;

                    if (! isset($taxDetails[$tax->name])) {
                        $taxDetails[$tax->name] = [
                            'rate'   => (float) $tax->rate,
                            'type'   => $tax->type,
                            'mode'   => $taxMode,
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

            $totalTaxAmount    = round($totalTaxAmount, 2);
            $totalExclusiveTax = round($totalExclusiveTax, 2);

            // Handle delivery address (must happen before zone fee lookup)
            $deliveryAddress = null;
            $deliveryCity = null;
            $deliveryZip = null;

            if ($validated['order_type'] === 'delivery') {
                if ($isCustomer && ! empty($validated['address_id'])) {
                    $address = Address::where('id', $validated['address_id'])
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
                $zone = Zone::findOrFail($validated['delivery_zone_id']);

                if (! $zone->is_active) {
                    throw new \Exception("Delivery is not currently available in the selected zone: {$zone->name}.");
                }

                $actualDeliveryFee = round((float) $zone->delivery_fee, 2);
                $deliveryZoneId = $zone->id;

                if ($freeDeliveryDiscount) {
                    $freeDeliveryAmount = $actualDeliveryFee;
                    $deliveryFee = 0;
                    $freeDeliveryDiscount->increment('used_count');
                } else {
                    $deliveryFee = $actualDeliveryFee;
                }
            }

            // Inclusive tax is already in totalAmount (customer paid it as part of price).
            // Exclusive tax is added on top.
            $finalAmount = $totalAmount - $discountAmount + $totalExclusiveTax + $deliveryFee + $takeawayAmount;

            // Get customer details
            $defaultCustomer = User::where('email', 'eatwella@gmail.com')->first();

            if ($isAttendant && ! empty($validated['customer_user_id'])) {
                // Staff linked to an existing customer
                $linkedCustomer = User::findOrFail($validated['customer_user_id']);
                $customerName   = $linkedCustomer->name;
                $customerEmail  = $linkedCustomer->email;
                $customerPhone  = $linkedCustomer->phone;
                $orderUserId    = $linkedCustomer->id;
            } elseif ($isAttendant) {
                // No customer provided — fall back to default platform customer
                $customerName   = $defaultCustomer->name;
                $customerEmail  = $defaultCustomer->email;
                $customerPhone  = $defaultCustomer->phone;
                $orderUserId    = $defaultCustomer->id;
            } elseif ($isCustomer) {
                $customerName   = $user->name;
                $customerEmail  = $user->email;
                $customerPhone  = $user->phone;
                $orderUserId    = $user->id;
            } else {
                // Guest — uses provided details
                $customerName   = $validated['customer_name'];
                $customerEmail  = $validated['customer_email'];
                $customerPhone  = $validated['customer_phone'] ?? null;
                $orderUserId    = null;
            }

            $attendantId = $isAttendant ? $user->id : null;

            // Generate order number upfront for all payment types
            $orderNumber = Order::generateOrderNumber();

            // Staff save-for-later: no payment_type provided
            if ($isAttendant && empty($validated['payment_type'])) {
                $deliveryPin = $validated['order_type'] === 'delivery'
                    ? str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT)
                    : null;

                $order = Order::create([
                    'order_number'         => $orderNumber,
                    'user_id'              => $orderUserId,
                    'attendant_id'         => $attendantId,
                    'order_type'           => $validated['order_type'],
                    'payment_type'         => null,
                    'payment_account_id'   => null,
                    'customer_email'       => $customerEmail,
                    'customer_name'        => $customerName,
                    'customer_phone'       => $customerPhone,
                    'table_number'         => $validated['table_number'] ?? null,
                    'delivery_address'     => $deliveryAddress,
                    'delivery_city'        => null,
                    'delivery_zip'         => null,
                    'delivery_zone_id'     => $deliveryZoneId,
                    'total_amount'         => $totalAmount,
                    'discount_amount'      => $discountAmount,
                    'tax_amount'           => $totalTaxAmount,
                    'delivery_fee'         => $deliveryFee,
                    'free_delivery_amount' => $freeDeliveryAmount,
                    'takeaway_amount'      => $takeawayAmount,
                    'tax_details'          => $taxDetails,
                    'discount_code'        => $discountCode,
                    'final_amount'         => $finalAmount,
                    'status'               => 'pending',
                    'delivery_pin'         => $deliveryPin,
                    'customer_note'        => $validated['customer_note'] ?? null,
                    'expires_at'           => null,
                    'outlet_id'            => $outletId ?? null,
                ]);

                foreach ($orderItemsData as $data) {
                    $order->orderItems()->create($data);
                }

                Invoice::create([
                    'order_id'       => $order->id,
                    'invoice_number' => 'INV-'.strtoupper(Str::random(10)),
                    'amount'         => $finalAmount,
                    'payment_status' => 'unpaid',
                    'payment_method' => null,
                ]);

                if (isset($cart) && $cart) {
                    $cart->delete();
                }

                return response()->json([
                    'message' => 'Order saved. Awaiting payment.',
                    'order'   => $order->load('orderItems', 'invoice'),
                ], 201);
            }

            $terminalWaiting = false;

            // Handle payment based on payment type
            if ($validated['payment_type'] === 'gateway') {
                // Initialize Payment with Paystack using our order number as reference
                $paymentResult = $this->paymentGateway->charge(
                    $finalAmount,
                    $customerEmail,
                    ['reference' => $orderNumber]
                );

                if ($paymentResult['status'] === 'failed') {
                    throw new \Exception('Payment initialization failed: '.($paymentResult['message'] ?? 'Unknown error'));
                }

                // Store the exact reference Paystack used (lowercased) so verification matches
                $paystackReference = $paymentResult['reference'];
                $orderStatus = 'init_payment';
                $paymentStatus = 'unpaid';
                $paymentMethod = 'paystack';
            } elseif ($validated['payment_type'] === 'loyalty_points') {
                if (! $user) {
                    throw new \Exception('You must be logged in to pay with loyalty points.');
                }

                $minPoints = $this->loyalty->getMinRedemption();

                if ($user->loyalty_points < $minPoints) {
                    throw new \Exception("You need a minimum of {$minPoints} loyalty points to redeem.");
                }

                $pointsNeeded = $this->loyalty->nairaToPoints($finalAmount);

                if ($user->loyalty_points < $pointsNeeded) {
                    throw new \Exception("Insufficient loyalty points. You need {$pointsNeeded} points for this order.");
                }

                $user->decrement('loyalty_points', $pointsNeeded);

                $orderStatus = 'confirmed';
                $paymentStatus = 'paid';
                $paymentMethod = 'loyalty_points';
            } else {
                // Cash / POS / Transfer payment
                $isTerminalPayment = false;

                if ($validated['payment_type'] === 'pos' && !empty($validated['payment_account_id'])) {
                    $paymentAccount = \App\Models\PaymentAccount::find($validated['payment_account_id']);
                    if ($paymentAccount && !empty($paymentAccount->terminal_id)) {
                        $paymentResult = $this->paymentGateway->pushToTerminal(
                            $paymentAccount->terminal_id,
                            (float) $finalAmount,
                            $orderNumber,
                            $customerEmail ?? 'pos@eatwella.com'
                        );

                        if ($paymentResult['status'] === 'failed') {
                            return response()->json(['message' => $paymentResult['message'] ?? 'Failed to push to terminal'], 422);
                        }

                        $orderStatus = 'init_payment';
                        $paymentStatus = 'pending';
                        $paymentMethod = 'pos';
                        $terminalWaiting = true;
                        $isTerminalPayment = true;
                        $paystackReference = $paymentResult['paystack_reference'] ?? null;
                        $postPaymentAction = $validated['action'] ?? null;
                    }
                }

                if (!$isTerminalPayment) {
                    if ($isAttendant) {
                        $action = $validated['action'] ?? null;
                        $orderStatus = match ($action) {
                            'send_to_kitchen' => 'in_kitchen',
                            'complete'        => 'completed',
                            default           => 'confirmed',
                        };
                        $paymentStatus = 'paid';
                    } else {
                        // Customer placed order with cash: pending until attendant confirms payment
                        $orderStatus = 'pending';
                        $paymentStatus = 'pending';
                    }

                    $paymentMethod = $validated['payment_type']; // cash, pos, or transfer
                }
            }

            // Create Order
            $expiresAt = null;

            $deliveryPin = null;
            if ($validated['order_type'] === 'delivery') {
                $deliveryPin = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            }

            $order = Order::create([
                'order_number'       => $orderNumber,
                'user_id'            => $orderUserId,
                'attendant_id'       => $attendantId,
                'order_type'         => $validated['order_type'],
                'payment_type'       => $validated['payment_type'],
                'payment_account_id' => $isAttendant && in_array($validated['payment_type'], ['cash', 'pos', 'transfer'])
                    ? ($validated['payment_account_id'] ?? null)
                    : null,
                'customer_email'       => $customerEmail,
                'customer_name'        => $customerName,
                'customer_phone'       => $customerPhone,
                'table_number'         => $validated['table_number'] ?? null,
                'delivery_address'     => $deliveryAddress,
                'delivery_city'        => null,
                'delivery_zip'         => null,
                'delivery_zone_id'     => $deliveryZoneId,
                'total_amount'         => $totalAmount,
                'discount_amount'      => $discountAmount,
                'tax_amount'           => $totalTaxAmount,
                'delivery_fee'         => $deliveryFee,
                'free_delivery_amount' => $freeDeliveryAmount,
                'takeaway_amount'      => $takeawayAmount,
                'tax_details'          => $taxDetails,
                'discount_code'        => $discountCode,
                'final_amount'         => $finalAmount,
                'status'               => $orderStatus,
                'delivery_pin'         => $deliveryPin,
                'customer_note'        => $validated['customer_note'] ?? null,
                'expires_at'           => $expiresAt,
                'outlet_id'            => $outletId ?? null,
                'paystack_reference'   => $paystackReference ?? null,
                'post_payment_action'  => $postPaymentAction ?? null,
            ]);

            // Create Order Items
            foreach ($orderItemsData as $data) {
                $order->orderItems()->create($data);
            }

            // Deduct stock for all immediately paid orders
            if (in_array($orderStatus, ['confirmed', 'in_kitchen', 'completed'])) {
                $order->deductItemsStock();
            }

            // Track kitchen/completion metadata if action was specified
            if ($orderStatus === 'in_kitchen') {
                $order->update([
                    'sent_to_kitchen_by_id' => $user->id,
                    'sent_to_kitchen_at'    => now(),
                ]);
            } elseif ($orderStatus === 'completed') {
                $order->update([
                    'completed_by_id' => $user->id,
                    'completed_at'    => now(),
                    'expires_at'      => null,
                ]);
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
            
            $responseMessage = 'Order created, proceed to payment';
            if ($terminalWaiting) {
                $responseMessage = 'Payment sent to Paystack Terminal. Awaiting customer payment.';
            } elseif (in_array($validated['payment_type'], ['cash', 'pos', 'transfer', 'loyalty_points'])) {
                $responseMessage = 'Order placed successfully';
            }

            $response = [
                'message' => $responseMessage,
                'order' => $order->load('orderItems', 'invoice', 'deliveryAgent', 'assignedBySupervisor'),
            ];

            if ($terminalWaiting) {
                $response['terminal_waiting'] = true;
            }

            if ($validated['payment_type'] === 'gateway') {
                $response['payment'] = [
                    'authorization_url' => $paymentResult['authorization_url'],
                    'reference' => $paymentResult['reference'],
                ];
            }

            // Send email after response data is ready (non-blocking)
            // Notify customer + admin only for immediately confirmed orders (not gateway — payment not confirmed yet)
            if (in_array($validated['payment_type'], ['cash', 'pos', 'transfer', 'loyalty_points'])) {
                try {
                    $checkoutUser = $orderUserId ? \App\Models\User::find($orderUserId) : null;
                    if (! $checkoutUser || $checkoutUser->shouldReceiveOrderEmails()) {
                        Mail::to($order->customer_email)->send(new OrderPlaced($order));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send order email: '.$e->getMessage());
                }

                $this->notifications->orderPlaced($order);
            }

            return response()->json($response, 201);
        });
    }

    private function applyInclusivePriceToOrderItems(Order $order): Order
    {
        $taxMode     = $this->getTaxMode();
        $activeTaxes = Tax::where('is_active', true)->get();

        if ($taxMode !== 'inclusive' || $activeTaxes->isEmpty()) {
            return $order;
        }

        $totalRate = $activeTaxes->sum(fn($t) => (float) $t->rate);

        $order->orderItems->each(function ($item) use ($totalRate) {
            if ($item->menu) {
                $item->menu->price = round((float) $item->menu->price * (1 + $totalRate / 100), 2);
            }
        });

        return $order;
    }

    public function trackOrder($identifier)
    {
        $order = Order::whereRaw('UPPER(order_number) = ?', [strtoupper($identifier)])
            ->orWhere('id', $identifier)
            ->with(['orderItems.menu', 'orderItems.packaging', 'invoice', 'deliveryAgent', 'assignedBySupervisor', 'review:id,order_id,user_id,rating,comment,created_at', 'review.user:id,name'])
            ->firstOrFail();

        $order->makeVisible('delivery_pin');

        return $this->applyInclusivePriceToOrderItems($order);
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOfflineSyncSideEffects;
use App\Models\InventoryLog;
use App\Models\Invoice;
use App\Models\Menu;
use App\Models\OfflineSyncBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutletMenuStock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfflineSyncController extends Controller
{
    /**
     * Process batch of offline orders created by staff when disconnected.
     */
    public function sync(Request $request): JsonResponse
    {
        if ($forbidden = $this->requireRole($request, ['attendant', 'supervisor', 'admin', 'cashier'])) {
            return $forbidden;
        }

        $validated = $request->validate([
            'outlet_id' => 'nullable|uuid|exists:outlets,id',
            'batch_id' => 'required|uuid',
            'orders' => 'required|array|min:1',
            'orders.*.offline_id' => 'required|uuid',
            'orders.*.offline_created_at' => 'required|date',
            'orders.*.order_type' => 'required|in:dine_in,dine,takeaway,pickup,delivery',
            'orders.*.payment_type' => 'nullable|in:cash,pos,transfer',
            'orders.*.customer_user_id' => 'nullable|uuid|exists:users,id',
            'orders.*.customer_name' => 'nullable|string|max:255',
            'orders.*.customer_email' => 'nullable|email|max:255',
            'orders.*.customer_phone' => 'nullable|string|max:20',
            'orders.*.table_number' => 'nullable|string|max:50',
            'orders.*.total_amount' => 'required|numeric|min:0',
            'orders.*.discount_amount' => 'nullable|numeric|min:0',
            'orders.*.tax_amount' => 'nullable|numeric|min:0',
            'orders.*.takeaway_amount' => 'nullable|numeric|min:0',
            'orders.*.final_amount' => 'required|numeric|min:0',
            'orders.*.items' => 'required|array|min:1',
            'orders.*.items.*.menu_id' => 'required|uuid|exists:menus,id',
            'orders.*.items.*.quantity' => 'required|integer|min:1',
            'orders.*.items.*.price' => 'required|numeric|min:0',
            'orders.*.items.*.subtotal' => 'required|numeric|min:0',
            'orders.*.items.*.packaging_id' => 'nullable|uuid|exists:takeaway_packagings,id',
            'orders.*.items.*.packaging_price' => 'nullable|numeric|min:0',
            'orders.*.items.*.local_stock_at_order' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();
        $outlet = $this->outletContext($request);
        $outletId = $outlet?->id
            ?? $validated['outlet_id'] ?? null
            ?? $request->header('X-Outlet-ID');

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

        // Idempotency check for batch
        $existingBatch = OfflineSyncBatch::find($validated['batch_id']);
        if ($existingBatch && $existingBatch->status === 'completed') {
            $syncedOrders = Order::where('sync_batch_id', $existingBatch->id)->get();
            $results = $syncedOrders->map(function ($order) {
                return [
                    'offline_id' => $order->offline_id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->sync_reconciliation ? 'synced_with_reconciliation' : 'synced',
                    'reconciliation' => $order->sync_reconciliation,
                ];
            });

            return response()->json([
                'message' => 'Batch already processed',
                'batch_id' => $existingBatch->id,
                'status' => 'completed',
                'total_orders' => $existingBatch->total_orders,
                'synced_count' => $existingBatch->synced_count,
                'reconciled_count' => $existingBatch->reconciled_count,
                'orders' => $results,
            ]);
        }

        $batch = $existingBatch ?? OfflineSyncBatch::create([
            'id' => $validated['batch_id'],
            'user_id' => $user->id,
            'outlet_id' => $outletId,
            'status' => 'processing',
            'total_orders' => count($validated['orders']),
        ]);

        // Default platform customer fallback
        $defaultCustomer = User::where('email', 'eatwella@gmail.com')->first();

        // Sort orders FIFO by offline_created_at
        $ordersPayload = $validated['orders'];
        usort($ordersPayload, function ($a, $b) {
            return strtotime($a['offline_created_at']) <=> strtotime($b['offline_created_at']);
        });

        $orderResults = [];
        $newlyCreatedOrderIds = [];
        $reconciledOrdersCount = 0;

        DB::transaction(function () use (
            $ordersPayload,
            $user,
            $outletId,
            $batch,
            $defaultCustomer,
            &$orderResults,
            &$newlyCreatedOrderIds,
            &$reconciledOrdersCount
        ) {
            foreach ($ordersPayload as $orderData) {
                // Check if this specific order was already synced (idempotent per order)
                $existingOrder = Order::where('offline_id', $orderData['offline_id'])->first();
                if ($existingOrder) {
                    $status = ! empty($existingOrder->sync_reconciliation) ? 'synced_with_reconciliation' : 'synced';
                    $orderResults[] = [
                        'offline_id' => $existingOrder->offline_id,
                        'order_id' => $existingOrder->id,
                        'order_number' => $existingOrder->order_number,
                        'status' => 'already_synced',
                        'reconciliation' => $existingOrder->sync_reconciliation,
                    ];
                    continue;
                }

                // Resolve customer
                $orderUserId = null;
                $customerName = null;
                $customerEmail = null;
                $customerPhone = null;

                if (! empty($orderData['customer_user_id'])) {
                    $linkedCustomer = User::find($orderData['customer_user_id']);
                    if ($linkedCustomer) {
                        $orderUserId = $linkedCustomer->id;
                        $customerName = $linkedCustomer->name;
                        $customerEmail = $linkedCustomer->email;
                        $customerPhone = $linkedCustomer->phone;
                    }
                }

                if (! $orderUserId && ! empty($orderData['customer_name']) && ! empty($orderData['customer_email'])) {
                    $customerName = $orderData['customer_name'];
                    $customerEmail = $orderData['customer_email'];
                    $customerPhone = $orderData['customer_phone'] ?? null;
                    $orderUserId = $defaultCustomer?->id;
                } elseif (! $orderUserId) {
                    $customerName = $defaultCustomer?->name ?? 'Walk-in Customer';
                    $customerEmail = $defaultCustomer?->email ?? 'eatwella@gmail.com';
                    $customerPhone = $defaultCustomer?->phone;
                    $orderUserId = $defaultCustomer?->id;
                }

                // Process Stock Reconciliation & Deduction atomically
                $reconciliationLogs = [];

                foreach ($orderData['items'] as $itemData) {
                    $menu = Menu::find($itemData['menu_id']);
                    if (! $menu) {
                        continue;
                    }

                    $requestedQty = (int) $itemData['quantity'];
                    $currentStock = 0;
                    $outletStock = null;

                    if ($outletId) {
                        $outletStock = OutletMenuStock::where('outlet_id', $outletId)
                            ->where('menu_id', $menu->id)
                            ->lockForUpdate()
                            ->first();

                        $currentStock = $outletStock ? (int) $outletStock->stock_quantity : 0;
                    } else {
                        $menuLocked = Menu::where('id', $menu->id)->lockForUpdate()->first();
                        $currentStock = $menuLocked ? (int) ($menuLocked->stock_quantity ?? 0) : 0;
                    }

                    // Top-up shortfall if needed
                    if ($currentStock < $requestedQty) {
                        $shortfall = $requestedQty - $currentStock;
                        $topUpBefore = $currentStock;
                        $topUpAfter = $currentStock + $shortfall;

                        if ($outletId && $outletStock) {
                            $outletStock->update(['stock_quantity' => $topUpAfter]);
                        } else {
                            Menu::where('id', $menu->id)->update(['stock_quantity' => $topUpAfter]);
                        }

                        InventoryLog::create([
                            'menu_id' => $menu->id,
                            'user_id' => $user->id,
                            'type' => 'offline_reconciliation',
                            'quantity_before' => $topUpBefore,
                            'quantity_changed' => $shortfall,
                            'quantity_after' => $topUpAfter,
                            'note' => "Offline reconciliation: topped up shortfall of {$shortfall} for order {$orderData['offline_id']}",
                        ]);

                        $reconciliationLogs[] = [
                            'menu_id' => $menu->id,
                            'menu_name' => $menu->name,
                            'shortfall' => $shortfall,
                            'topped_up_to' => $topUpAfter,
                        ];

                        $currentStock = $topUpAfter;
                    }

                    // Deduct stock for order
                    $deductBefore = $currentStock;
                    $deductAfter = max(0, $currentStock - $requestedQty);

                    if ($outletId && $outletStock) {
                        $outletStock->update(['stock_quantity' => $deductAfter]);
                    } else {
                        Menu::where('id', $menu->id)->update(['stock_quantity' => $deductAfter]);
                    }

                    InventoryLog::create([
                        'menu_id' => $menu->id,
                        'user_id' => $user->id,
                        'type' => 'deduction',
                        'quantity_before' => $deductBefore,
                        'quantity_changed' => -$requestedQty,
                        'quantity_after' => $deductAfter,
                        'note' => 'Offline order deduction',
                    ]);
                }

                $hasReconciliation = ! empty($reconciliationLogs);
                if ($hasReconciliation) {
                    $reconciledOrdersCount++;
                }

                // Create Order
                $orderNumber = Order::generateOrderNumber();
                $offlineCreatedAt = date('Y-m-d H:i:s', strtotime($orderData['offline_created_at']));

                $mappedOrderType = match ($orderData['order_type']) {
                    'dine_in' => 'dine',
                    'takeaway' => 'pickup',
                    default => $orderData['order_type'],
                };

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'offline_id' => $orderData['offline_id'],
                    'offline_created_at' => $offlineCreatedAt,
                    'sync_batch_id' => $batch->id,
                    'sync_reconciliation' => $hasReconciliation ? $reconciliationLogs : null,
                    'user_id' => $orderUserId,
                    'attendant_id' => $user->id,
                    'outlet_id' => $outletId,
                    'order_type' => $mappedOrderType,
                    'payment_type' => $orderData['payment_type'] ?? 'cash',
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'table_number' => $orderData['table_number'] ?? null,
                    'total_amount' => $orderData['total_amount'],
                    'discount_amount' => $orderData['discount_amount'] ?? 0,
                    'tax_amount' => $orderData['tax_amount'] ?? 0,
                    'takeaway_amount' => $orderData['takeaway_amount'] ?? 0,
                    'final_amount' => $orderData['final_amount'],
                    'status' => 'completed',
                    'completed_by_id' => $user->id,
                    'completed_at' => $offlineCreatedAt,
                    'created_at' => $offlineCreatedAt,
                ]);

                // Create Order Items
                foreach ($orderData['items'] as $itemData) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_id' => $itemData['menu_id'],
                        'quantity' => $itemData['quantity'],
                        'price' => $itemData['price'],
                        'subtotal' => $itemData['subtotal'],
                        'packaging_id' => $itemData['packaging_id'] ?? null,
                        'packaging_price' => $itemData['packaging_price'] ?? 0,
                        'local_stock_at_order' => $itemData['local_stock_at_order'] ?? null,
                    ]);
                }

                // Create Invoice
                Invoice::create([
                    'order_id' => $order->id,
                    'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
                    'amount' => $order->final_amount,
                    'payment_status' => 'paid',
                    'payment_method' => $order->payment_type,
                ]);

                $newlyCreatedOrderIds[] = $order->id;

                $orderResults[] = [
                    'offline_id' => $order->offline_id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $hasReconciliation ? 'synced_with_reconciliation' : 'synced',
                    'reconciliation' => $order->sync_reconciliation,
                ];
            }

            // Update batch stats
            $syncedCount = count($orderResults);
            $batch->update([
                'status' => 'completed',
                'synced_count' => $syncedCount,
                'reconciled_count' => $reconciledOrdersCount,
                'synced_at' => now(),
            ]);
        });

        // Dispatch side-effects (push notifications) asynchronously to queue
        if (! empty($newlyCreatedOrderIds)) {
            ProcessOfflineSyncSideEffects::dispatch($newlyCreatedOrderIds, $reconciledOrdersCount);
        }

        return response()->json([
            'message' => 'Offline orders synced successfully',
            'batch_id' => $batch->id,
            'status' => 'completed',
            'total_orders' => $batch->total_orders,
            'synced_count' => count($orderResults),
            'reconciled_count' => $reconciledOrdersCount,
            'orders' => $orderResults,
        ]);
    }
}

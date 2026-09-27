<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryLog;
use App\Models\Invoice;
use App\Models\Menu;
use App\Models\OfflineSyncBatch;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\OutletMenuStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $attendant;
    protected User $defaultCustomer;
    protected Outlet $outlet;
    protected Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendant = User::factory()->create([
            'role' => 'attendant',
        ]);

        $this->defaultCustomer = User::factory()->create([
            'email' => 'eatwella@gmail.com',
            'name' => 'Eatwella Customer',
        ]);

        $state = \App\Models\State::create(['name' => 'Lagos']);
        $city = \App\Models\City::create(['state_id' => $state->id, 'name' => 'Ikeja']);

        $this->outlet = Outlet::create([
            'id' => (string) Str::uuid(),
            'name' => 'Main Outlet',
            'address' => '123 Main St',
            'phone' => '08012345678',
            'city_id' => $city->id,
            'is_active' => true,
        ]);

        $this->attendant->update(['outlet_id' => $this->outlet->id]);

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'name' => 'Food',
        ]);

        $this->menu = Menu::create([
            'id' => (string) Str::uuid(),
            'category_id' => $category->id,
            'name' => 'Jollof Rice',
            'price' => 1500,
            'images' => json_encode([]),
            'stock_quantity' => 10,
            'is_available' => true,
        ]);

        OutletMenuStock::create([
            'id' => (string) Str::uuid(),
            'outlet_id' => $this->outlet->id,
            'menu_id' => $this->menu->id,
            'stock_quantity' => 5,
        ]);
    }

    public function test_unauthorized_user_cannot_sync_offline_orders()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->postJson('/api/attendant/offline-sync', [
                'batch_id' => (string) Str::uuid(),
                'orders' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_successful_offline_sync_without_reconciliation()
    {
        $batchId = (string) Str::uuid();
        $offlineId = (string) Str::uuid();

        $payload = [
            'batch_id' => $batchId,
            'orders' => [
                [
                    'offline_id' => $offlineId,
                    'offline_created_at' => now()->subMinutes(10)->toIso8601String(),
                    'order_type' => 'dine_in',
                    'payment_type' => 'cash',
                    'customer_name' => 'John Doe',
                    'customer_email' => 'john@example.com',
                    'total_amount' => 3000,
                    'final_amount' => 3000,
                    'items' => [
                        [
                            'menu_id' => $this->menu->id,
                            'quantity' => 2,
                            'price' => 1500,
                            'subtotal' => 3000,
                            'local_stock_at_order' => 5,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->attendant)
            ->postJson('/api/attendant/offline-sync', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'completed',
                'synced_count' => 1,
                'reconciled_count' => 0,
            ]);

        $this->assertDatabaseHas('orders', [
            'offline_id' => $offlineId,
            'status' => 'completed',
            'outlet_id' => $this->outlet->id,
        ]);

        $outletStock = OutletMenuStock::where('outlet_id', $this->outlet->id)
            ->where('menu_id', $this->menu->id)
            ->first();

        $this->assertEquals(3, $outletStock->stock_quantity); // 5 - 2 = 3
    }

    public function test_offline_sync_reconciliation_when_oversold()
    {
        // Outlet stock is 5. Staff A sells 3, Staff B sells 4 (total 7 sold offline)
        $batchId = (string) Str::uuid();
        $offlineId1 = (string) Str::uuid();
        $offlineId2 = (string) Str::uuid();

        $payload = [
            'batch_id' => $batchId,
            'orders' => [
                [
                    'offline_id' => $offlineId1,
                    'offline_created_at' => now()->subMinutes(20)->toIso8601String(),
                    'order_type' => 'takeaway',
                    'payment_type' => 'cash',
                    'total_amount' => 4500,
                    'final_amount' => 4500,
                    'items' => [
                        [
                            'menu_id' => $this->menu->id,
                            'quantity' => 3,
                            'price' => 1500,
                            'subtotal' => 4500,
                            'local_stock_at_order' => 5,
                        ],
                    ],
                ],
                [
                    'offline_id' => $offlineId2,
                    'offline_created_at' => now()->subMinutes(10)->toIso8601String(),
                    'order_type' => 'takeaway',
                    'payment_type' => 'pos',
                    'total_amount' => 6000,
                    'final_amount' => 6000,
                    'items' => [
                        [
                            'menu_id' => $this->menu->id,
                            'quantity' => 4,
                            'price' => 1500,
                            'subtotal' => 6000,
                            'local_stock_at_order' => 2,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->attendant)
            ->postJson('/api/attendant/offline-sync', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'completed',
                'synced_count' => 2,
                'reconciled_count' => 1,
            ]);

        // After Order 1 (3 qty): stock 5 - 3 = 2
        // Order 2 needs 4 qty, but stock is 2. Shortfall = 4 - 2 = 2.
        // Reconcile: stock 2 + 2 = 4, then deduct 4 -> stock ends at 0.
        $outletStock = OutletMenuStock::where('outlet_id', $this->outlet->id)
            ->where('menu_id', $this->menu->id)
            ->first();

        $this->assertEquals(0, $outletStock->stock_quantity);

        $this->assertDatabaseHas('inventory_logs', [
            'menu_id' => $this->menu->id,
            'type' => 'offline_reconciliation',
            'quantity_changed' => 2,
        ]);

        $order2 = Order::where('offline_id', $offlineId2)->first();
        $this->assertNotNull($order2->sync_reconciliation);
        $this->assertEquals(2, $order2->sync_reconciliation[0]['shortfall']);
    }

    public function test_batch_sync_is_idempotent()
    {
        $batchId = (string) Str::uuid();
        $offlineId = (string) Str::uuid();

        $payload = [
            'batch_id' => $batchId,
            'orders' => [
                [
                    'offline_id' => $offlineId,
                    'offline_created_at' => now()->toIso8601String(),
                    'order_type' => 'dine_in',
                    'payment_type' => 'cash',
                    'total_amount' => 1500,
                    'final_amount' => 1500,
                    'items' => [
                        [
                            'menu_id' => $this->menu->id,
                            'quantity' => 1,
                            'price' => 1500,
                            'subtotal' => 1500,
                        ],
                    ],
                ],
            ],
        ];

        // First attempt
        $this->actingAs($this->attendant)
            ->postJson('/api/attendant/offline-sync', $payload)
            ->assertStatus(200);

        // Second attempt with exact same payload
        $response = $this->actingAs($this->attendant)
            ->postJson('/api/attendant/offline-sync', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Batch already processed',
                'batch_id' => $batchId,
            ]);

        // Stock deducted only once: 5 - 1 = 4
        $outletStock = OutletMenuStock::where('outlet_id', $this->outlet->id)
            ->where('menu_id', $this->menu->id)
            ->first();

        $this->assertEquals(4, $outletStock->stock_quantity);
    }
}

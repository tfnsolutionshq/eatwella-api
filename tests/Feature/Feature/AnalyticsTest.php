<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_view_analytics_summary()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        OrderItem::query()->delete();
        \App\Models\Invoice::query()->delete();
        Order::query()->delete();

        // Create some orders
        $menu = Menu::factory()->create(['price' => 10]);
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'order_type' => 'pickup',
            'payment_type' => 'cash',
            'customer_email' => 'test@example.com',
            'customer_name' => 'Test Customer',
            'total_amount' => 20,
            'final_amount' => 20,
            'status' => 'completed',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'menu_id' => $menu->id,
            'quantity' => 2,
            'price' => 10,
            'subtotal' => 20,
        ]);

        $response = $this->getJson('/api/admin/analytics/summary');

        $response->assertStatus(200)
            ->assertJson([
                'total_revenue' => 20,
                'total_orders' => 1,
                'average_order_value' => 20,
            ]);
    }

    public function test_admin_can_view_top_menus()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        OrderItem::query()->delete();
        \App\Models\Invoice::query()->delete();
        Order::query()->delete();

        $menu1 = Menu::factory()->create(['name' => 'Pizza', 'price' => 20]);
        $menu2 = Menu::factory()->create(['name' => 'Burger', 'price' => 10]);

        // Order 1: 2 Pizzas
        $order1 = Order::create(['order_number' => 'ORD-1', 'order_type' => 'pickup', 'payment_type' => 'cash', 'customer_email' => 'a@b.com', 'customer_name' => 'Test Customer', 'total_amount' => 40, 'final_amount' => 40, 'status' => 'completed']);
        OrderItem::create(['order_id' => $order1->id, 'menu_id' => $menu1->id, 'quantity' => 2, 'price' => 20, 'subtotal' => 40]);

        // Order 2: 1 Burger
        $order2 = Order::create(['order_number' => 'ORD-2', 'order_type' => 'pickup', 'payment_type' => 'cash', 'customer_email' => 'a@b.com', 'customer_name' => 'Test Customer', 'total_amount' => 10, 'final_amount' => 10, 'status' => 'completed']);
        OrderItem::create(['order_id' => $order2->id, 'menu_id' => $menu2->id, 'quantity' => 1, 'price' => 10, 'subtotal' => 10]);

        $response = $this->getJson('/api/admin/analytics/top-menus');

        $response->assertStatus(200);
        $this->assertEquals($menu1->id, $response->json('0.menu_id'));
        $this->assertEquals(2, $response->json('0.total_sold'));
    }

    public function test_admin_can_view_daily_sales()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        OrderItem::query()->delete();
        \App\Models\Invoice::query()->delete();
        Order::query()->delete();

        Order::create([
            'order_number' => 'ORD-3',
            'order_type' => 'pickup',
            'payment_type' => 'cash',
            'customer_email' => 'a@b.com',
            'customer_name' => 'Test Customer',
            'total_amount' => 50,
            'final_amount' => 50,
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/admin/analytics/daily-sales');

        $response->assertStatus(200);
        $this->assertEquals(50, $response->json('0.revenue'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GatewayOrderStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_gateway_checkout_lifecycle()
    {
        Mail::fake();

        // 1. Setup Category and Menu
        $category = Category::create([
            'name' => 'Test Category',
            'is_active' => true,
        ]);

        $menu = Menu::create([
            'category_id' => $category->id,
            'name' => 'Test Pizza',
            'price' => 50.00,
            'is_available' => true,
            'images' => ['pizza.jpg'],
        ]);

        // Mock Paystack Gateway API calls
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/mock-checkout',
                    'access_code' => 'MOCK_CODE',
                    'reference' => 'ew_mock_ref',
                ]
            ], 200),
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 5000,
                    'gateway_response' => 'Successful',
                    'paid_at' => now()->toIso8601String(),
                ]
            ], 200),
        ]);

        // 2. Perform Customer Gateway Checkout
        $response = $this->postJson('/api/checkout', [
            'order_type' => 'pickup',
            'payment_type' => 'gateway',
            'customer_email' => 'customer@example.com',
            'customer_name' => 'Test Customer',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('order.id');
        $orderNumber = $response->json('order.order_number');

        // Verify the order has status 'init_payment'
        $order = Order::find($orderId);
        $this->assertEquals('init_payment', $order->status);

        // 3. Admin user logs in to fetch pending orders
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        // Verify that 'init_payment' order does NOT show up in pending orders list
        $adminResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/orders?status=pending');

        $adminResponse->assertStatus(200);
        $ordersData = $adminResponse->json('data');
        
        // Assert that the order is not in the pending orders returned to staff
        $orderIdsInPending = collect($ordersData)->pluck('id')->toArray();
        $this->assertNotContains($orderId, $orderIdsInPending);

        // 4. Simulate payment callback / webhook / status check
        // Webhook handles success, updates the order to 'confirmed'
        // Let's use the public orderStatus endpoint instead, which is exactly meant to check status and handles webhook delays!
        $statusResponse = $this->getJson("/api/payment/status?reference={$orderNumber}");
        $statusResponse->assertStatus(200);

        // Verify that status has changed to confirmed
        $order->refresh();
        $this->assertEquals('confirmed', $order->status);

        // Verify the invoice status is 'paid'
        $this->assertEquals('paid', $order->invoice->payment_status);

        // 5. Admin retrieves pending orders again: it's not pending (it's confirmed)
        // Admin retrieves confirmed orders
        $confirmedResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/orders?status=confirmed');

        $confirmedResponse->assertStatus(200);
        $confirmedOrdersData = $confirmedResponse->json('data');
        $orderIdsInConfirmed = collect($confirmedOrdersData)->pluck('id')->toArray();
        $this->assertContains($orderId, $orderIdsInConfirmed);
    }
}

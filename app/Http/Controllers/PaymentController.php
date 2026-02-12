<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Initialize payment - returns Paystack authorization URL
     */
    public function initializePayment(Request $request)
    {
        $validated = $request->validate([
            'customer_email' => 'required|email',
            'amount' => 'required|numeric|min:0',
            'callback_url' => 'nullable|url'
        ]);

        $paymentGateway = app(\App\Interfaces\PaymentGatewayInterface::class);
        
        $result = $paymentGateway->charge(
            $validated['amount'],
            $validated['customer_email'],
            ['callback_url' => $validated['callback_url'] ?? null]
        );

        return response()->json($result);
    }

    /**
     * Verify payment after redirect
     */
    public function verifyPayment(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return response()->json(['message' => 'No reference provided'], 400);
        }

        $paymentGateway = app(\App\Interfaces\PaymentGatewayInterface::class);
        $result = $paymentGateway->verifyTransaction($reference);

        return response()->json($result);
    }

    /**
     * Get order status after payment (callback endpoint)
     */
    public function orderStatus(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return response()->json(['message' => 'No reference provided'], 400);
        }

        $order = Order::where('order_number', $reference)
            ->with(['orderItems.menu', 'invoice'])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'order' => $order,
            'payment_status' => $order->invoice->payment_status,
            'order_status' => $order->status
        ]);
    }

    /**
     * Paystack webhook handler
     */
    public function webhook(Request $request)
    {
        // Verify webhook signature
        $signature = $request->header('x-paystack-signature');
        $body = $request->getContent();
        
        if (!$signature || hash_hmac('sha512', $body, config('services.paystack.secret_key')) !== $signature) {
            Log::warning('Invalid Paystack webhook signature');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        // Handle charge.success event
        if ($event === 'charge.success') {
            $reference = $data['reference'];
            $status = $data['status'];

            // Find order by reference and update status
            $order = Order::where('order_number', $reference)->first();
            
            if ($order && $status === 'success') {
                $order->update(['status' => 'confirmed']);
                
                // Update invoice to paid
                $order->invoice()->update(['payment_status' => 'paid']);
                
                // Send confirmation email
                \Mail::to($order->customer_email)->send(new \App\Mail\OrderPlaced($order));
                
                Log::info("Order {$reference} payment confirmed via webhook");
            }
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }
}

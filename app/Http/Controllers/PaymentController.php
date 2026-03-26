<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    /**
     * Get all payments with totals (Admin and Attendant)
     */
    public function index(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'attendant'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $perPage = $request->input('per_page', 15);

        // Calculate totals
        $totalRevenue = Invoice::where('payment_status', 'paid')->sum('amount');
        $totalPending = Invoice::whereIn('payment_status', ['pending', 'unpaid'])->count();
        $totalCompleted = Invoice::where('payment_status', 'paid')->count();
        $totalTransactions = Invoice::count();

        // Get paginated payments
        $payments = Invoice::with(['order.user', 'order.attendant'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_pending' => $totalPending,
                'total_completed' => $totalCompleted,
                'total_transactions' => $totalTransactions
            ],
            'payments' => $payments
        ]);
    }

    /**
     * Initialize payment - returns Paystack authorization URL
     */
    public function initializePayment(Request $request)
    {
        $validated = $request->validate([
            'customer_email' => 'required|email',
            'amount' => 'required|numeric|min:0',
            'callback_url' => 'nullable|url',
            'discount_code' => 'nullable|string'
        ]);

        $finalAmount = $validated['amount'];
        $discountAmount = 0;
        $discountCode = null;

        if (!empty($validated['discount_code'])) {
            $discount = \App\Models\Discount::where('code', strtoupper($validated['discount_code']))->first();

            if ($discount && $discount->isValid()) {
                $discountAmount = $discount->calculateDiscount($validated['amount']);
                $finalAmount = max(0, $validated['amount'] - $discountAmount);
                $discountCode = $discount->code;
            }
        }

        $paymentGateway = app(\App\Interfaces\PaymentGatewayInterface::class);

        $result = $paymentGateway->charge(
            $finalAmount,
            $validated['customer_email'],
            ['callback_url' => $validated['callback_url'] ?? null]
        );

        $result['discount_applied'] = $discountCode;
        $result['discount_amount'] = $discountAmount;
        $result['original_amount'] = $validated['amount'];
        $result['final_amount'] = $finalAmount;

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

        // If still pending, verify with Paystack (handles webhook delays)
        if ($order->status === 'pending') {
            $paymentGateway = app(\App\Interfaces\PaymentGatewayInterface::class);
            $verificationResult = $paymentGateway->verifyTransaction($reference);

            if ($verificationResult['status'] === 'success') {
                // Update order and invoice status
                $order->update(['status' => 'confirmed']);
                $order->invoice()->update(['payment_status' => 'paid']);

                // Refresh order data
                $order->refresh();
                $order->load(['orderItems.menu', 'invoice']);

                Log::info("Order {$reference} payment confirmed via status check (webhook delay)");
            }
        }

        return response()->json([
            'order' => $order,
            'payment_status' => $order->invoice->payment_status,
            'order_status' => $order->status
        ]);
    }

    /**
     * Payment callback endpoint (redirects to frontend receipt page)
     */
    public function paymentCallback(Request $request)
    {
        $reference = $request->query('reference') ?: $request->query('trxref');

        if (!$reference) {
            return redirect('https://eatwella.ng');
        }

        $order = Order::where('order_number', $reference)->first();

        if (!$order) {
            return redirect('https://eatwella.ng');
        }

        // If still pending, verify with Paystack
        if ($order->status === 'pending') {
            $paymentGateway = app(\App\Interfaces\PaymentGatewayInterface::class);
            $verificationResult = $paymentGateway->verifyTransaction($reference);

            if ($verificationResult['status'] === 'success') {
                $order->update(['status' => 'confirmed']);
                $order->invoice()->update(['payment_status' => 'paid']);
                Mail::to($order->customer_email)->send(new \App\Mail\OrderPlaced($order));
                Log::info("Order {$reference} payment confirmed via callback");
            }
        }

        return redirect('https://eatwella.ng/receipt/' . $order->id);
    }

    /**
     * Paystack webhook handler
     */
    public function webhook(Request $request)
    {
        // Log webhook received for debugging
        Log::info('Webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        // Verify webhook signature
        $signature = $request->header('x-paystack-signature');
        $body = $request->getContent();

        if (!$signature || hash_hmac('sha512', $body, config('services.paystack.secret_key')) !== $signature) {
            Log::warning('Invalid Paystack webhook signature');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info('Webhook event processed', ['event' => $event, 'reference' => $data['reference'] ?? 'N/A']);

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
                Mail::to($order->customer_email)->send(new \App\Mail\OrderPlaced($order));

                Log::info("Order {$reference} payment confirmed via webhook");
            } else {
                Log::warning("Order not found or status mismatch", ['reference' => $reference, 'status' => $status]);
            }
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }
}

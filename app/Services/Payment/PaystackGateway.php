<?php

namespace App\Services\Payment;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackGateway implements PaymentGatewayInterface
{
    protected $secretKey;
    protected $publicKey;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
    }

    public function charge(float $amount, string $email, array $data): array
    {
        $payload = [
            'email'        => $email,
            'amount'       => (int) ($amount * 100),
            'callback_url' => $data['callback_url'] ?? config('services.paystack.callback_url'),
        ];

        if (!empty($data['reference'])) {
            $payload['reference'] = strtolower($data['reference']);
        }

        if (app()->isProduction()) {
            $splitCode = config('services.paystack.split_code');
            if ($splitCode) {
                $payload['split_code'] = $splitCode;
            }
        }
        $response = Http::withToken($this->secretKey)
            ->post('https://api.paystack.co/transaction/initialize', $payload);

        if ($response->successful() && $response->json('status')) {
            return [
                'status' => 'pending',
                'authorization_url' => $response->json('data.authorization_url'),
                'access_code' => $response->json('data.access_code'),
                'reference' => $response->json('data.reference'),
                'payment_method' => 'paystack'
            ];
        }

        return [
            'status' => 'failed',
            'message' => $response->json('message') ?? 'Payment initialization failed'
        ];
    }

    public function verifyTransaction($reference)
    {
        $response = Http::withToken($this->secretKey)
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        Log::info('Paystack transaction verify', [
            'reference'   => $reference,
            'status_code' => $response->status(),
            'body'        => $response->json(),
        ]);

        if ($response->successful() && $response->json('data.status') === 'success') {
            return [
                'status'           => 'success',
                'reference'        => $reference,
                'amount'           => $response->json('data.amount') / 100,
                'gateway_response' => $response->json('data.gateway_response'),
                'payment_method'   => 'paystack',
                'paid_at'          => $response->json('data.paid_at'),
            ];
        }

        return [
            'status'  => 'failed',
            'message' => $response->json('message') ?? 'Verification failed',
        ];
    }

    public function pushToTerminal(string $terminalId, float $amount, string $reference, string $customerEmail = 'pos@eatwella.com'): array
    {
        // 1. Create or retrieve Paystack customer using internal POS email to prevent Paystack sending its own receipt
        $customerResponse = Http::withToken($this->secretKey)
            ->post('https://api.paystack.co/customer', [
                'email'      => 'pos-terminal@eatwella.ng',
                'first_name' => 'POS',
                'last_name'  => 'Terminal'
            ]);

        if (!$customerResponse->successful() || empty($customerResponse->json('data.customer_code'))) {
            return [
                'status'  => 'failed',
                'message' => 'Failed to initialize Paystack customer for terminal'
            ];
        }
        
        $customerCode = $customerResponse->json('data.customer_code');

        // 2. Create Payment Request (Invoice)
        $prResponse = Http::withToken($this->secretKey)
            ->post('https://api.paystack.co/paymentrequest', [
                'customer'          => $customerCode,
                'amount'            => (int) ($amount * 100),
                'description'       => "Order {$reference}",
                'send_notification' => false,
                'has_invoice'       => false,
            ]);

        if (!$prResponse->successful() || empty($prResponse->json('data.id'))) {
            return [
                'status'  => 'failed',
                'message' => 'Failed to create Paystack payment request for terminal'
            ];
        }

        $invoiceId = $prResponse->json('data.id');
        $offlineRef = $prResponse->json('data.offline_reference') ?? $reference;
        $requestCode = $prResponse->json('data.request_code');

        // 3. Push Event to Terminal
        $payload = [
            'type'   => 'invoice',
            'action' => 'process',
            'data'   => [
                'id'        => $invoiceId,
                'reference' => $offlineRef
            ]
        ];

        $response = Http::withToken($this->secretKey)
            ->post("https://api.paystack.co/terminal/{$terminalId}/event", $payload);

        Log::info('Paystack terminal push', [
            'terminal_id'  => $terminalId,
            'invoice_id'   => $invoiceId,
            'offline_ref'  => $offlineRef,
            'request_code' => $requestCode,
            'status_code'  => $response->status(),
            'body'         => $response->json(),
        ]);

        if ($response->successful() && $response->json('status')) {
            return [
                'status'              => 'success',
                'message'             => $response->json('message') ?? 'Event pushed successfully',
                'data'                => $response->json('data'),
                'paystack_reference'  => $offlineRef,
                'request_code'        => $requestCode,
            ];
        }

        return [
            'status'  => 'failed',
            'message' => $response->json('message') ?? 'Failed to push event to terminal'
        ];
    }
}

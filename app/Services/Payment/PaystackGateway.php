<?php

namespace App\Services\Payment;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

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
        // Initialize transaction and return authorization URL
        $payload = [
            'email' => $email,
            'amount' => (int) ($amount * 100),
            'callback_url' => $data['callback_url'] ?? null,
            'split_code' => env('PAYSTACK_SPLIT_CODE'),
        ];
        if (!empty($data['reference'])) {
            $payload['reference'] = strtolower($data['reference']);
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

        if ($response->successful() && $response->json('data.status') === 'success') {
            return [
                'status' => 'success',
                'reference' => $reference,
                'amount' => $response->json('data.amount') / 100,
                'gateway_response' => $response->json('data.gateway_response'),
                'payment_method' => 'paystack',
                'paid_at' => $response->json('data.paid_at')
            ];
        }

        return [
            'status' => 'failed',
            'message' => $response->json('message') ?? 'Verification failed'
        ];
    }
}

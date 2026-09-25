<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
$token = config('services.paystack.secret_key');

// 1. Create a customer
$custRes = Http::withToken($token)->post('https://api.paystack.co/customer', [
    'email' => 'eatwella'.time().'@gmail.com',
    'first_name' => 'Eatwella',
    'last_name' => 'Customer'
]);

$customerCode = $custRes->json('data.customer_code');
if (!$customerCode) die("Customer failed: " . json_encode($custRes->json()));

// 2. Create payment request
$prRes = Http::withToken($token)->post('https://api.paystack.co/paymentrequest', [
    'customer' => $customerCode,
    'amount' => 1000,
    'description' => 'Order TEST'
]);

$invoiceId = $prRes->json('data.id');
$offlineRef = $prRes->json('data.offline_reference') ?? 'TEST-' . time();
if (!$invoiceId) die("PR failed: " . json_encode($prRes->json()));

echo "Invoice ID: $invoiceId\n";

// 3. Push to terminal
$pushRes = Http::withToken($token)->post('https://api.paystack.co/terminal/2PSTAX69/event', [
    'type' => 'invoice',
    'action' => 'process',
    'data' => [
        'id' => $invoiceId,
        'reference' => $offlineRef
    ]
]);

echo json_encode($pushRes->json(), JSON_PRETTY_PRINT);

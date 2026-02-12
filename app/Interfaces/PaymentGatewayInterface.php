<?php

namespace App\Interfaces;

interface PaymentGatewayInterface
{
    /**
     * Charge the customer for the order.
     *
     * @param float $amount The amount to charge.
     * @param string $email The customer's email.
     * @param array $data Additional payment data (e.g., token, reference).
     * @return array Response from the gateway (must contain 'status' => 'success' or 'failed').
     */
    public function charge(float $amount, string $email, array $data): array;
}

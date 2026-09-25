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

    /**
     * Push a payment request directly to a physical terminal.
     *
     * @param string $terminalId The ID of the terminal to push to.
     * @param float $amount The amount to charge.
     * @param string $reference The unique reference for the transaction.
     * @return array Response from the gateway.
     */
    public function pushToTerminal(string $terminalId, float $amount, string $reference, string $customerEmail = 'pos@eatwella.com'): array;
}

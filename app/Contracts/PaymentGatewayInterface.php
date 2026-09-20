<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function createPayment(array $payload): array;

    public function getPaymentStatus(string $transactionId): array;

    public function verifyCallback(string $payload, array $headers): bool;
}

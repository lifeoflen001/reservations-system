<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\ProviderNotConfiguredException;

class NullPaymentGateway implements PaymentGatewayInterface
{
    public function createPayment(array $payload): array
    {
        throw new ProviderNotConfiguredException('Payment gateway is not configured.');
    }

    public function getPaymentStatus(string $transactionId): array
    {
        throw new ProviderNotConfiguredException('Payment gateway is not configured.');
    }

    public function verifyCallback(string $payload, array $headers): bool
    {
        return false;
    }
}

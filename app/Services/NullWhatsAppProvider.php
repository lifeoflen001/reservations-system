<?php

namespace App\Services;

use App\Contracts\WhatsAppProviderInterface;
use App\Exceptions\ProviderNotConfiguredException;

class NullWhatsAppProvider implements WhatsAppProviderInterface
{
    public function sendTemplate(string $recipient, string $template, array $parameters = []): string
    {
        throw new ProviderNotConfiguredException('WhatsApp provider is not configured.');
    }

    public function verifyWebhook(string $payload, array $headers): bool
    {
        return false;
    }
}

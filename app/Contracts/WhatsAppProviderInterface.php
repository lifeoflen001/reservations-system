<?php

namespace App\Contracts;

interface WhatsAppProviderInterface
{
    public function sendTemplate(string $recipient, string $template, array $parameters = []): string;

    public function verifyWebhook(string $payload, array $headers): bool;
}

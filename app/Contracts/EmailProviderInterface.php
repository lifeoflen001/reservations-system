<?php

namespace App\Contracts;

interface EmailProviderInterface
{
    public function send(string $recipient, string $subject, string $body, ?string $actionUrl = null, ?string $title = null): void;
}

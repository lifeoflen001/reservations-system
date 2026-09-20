<?php

namespace App\Contracts;

interface EmailProviderInterface
{
    public function send(string $recipient, string $subject, string $body): void;
}

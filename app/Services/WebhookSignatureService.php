<?php

namespace App\Services;

class WebhookSignatureService
{
    public function sign(string $payload, string $secret, int $timestamp): string
    {
        return 'v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    public function verify(string $payload, string $secret, ?string $signature, ?string $timestamp, int $tolerance = 300): bool
    {
        if (! $signature || ! $timestamp || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals($this->sign($payload, $secret, (int) $timestamp), $signature);
    }
}

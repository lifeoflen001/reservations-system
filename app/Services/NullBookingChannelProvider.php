<?php

namespace App\Services;

use App\Contracts\BookingChannelInterface;
use App\Exceptions\ProviderNotConfiguredException;

class NullBookingChannelProvider implements BookingChannelInterface
{
    public function testConnection(): bool
    {
        throw new ProviderNotConfiguredException('Booking channel is not configured.');
    }

    public function pullReservations(): array
    {
        throw new ProviderNotConfiguredException('Booking channel is not configured.');
    }

    public function pushAvailability(array $payload): void
    {
        throw new ProviderNotConfiguredException('Booking channel is not configured.');
    }

    public function pushRates(array $payload): void
    {
        throw new ProviderNotConfiguredException('Booking channel is not configured.');
    }
}

<?php

namespace App\Contracts;

interface BookingChannelInterface
{
    public function testConnection(): bool;

    public function pullReservations(): array;

    public function pushAvailability(array $payload): void;

    public function pushRates(array $payload): void;
}

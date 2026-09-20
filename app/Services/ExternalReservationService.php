<?php

namespace App\Services;

use App\Models\ChannelConnection;
use App\Models\ExternalReservation;

class ExternalReservationService
{
    public function record(ChannelConnection $connection, string $externalId, array $payload): ExternalReservation
    {
        return ExternalReservation::updateOrCreate(['channel_connection_id' => $connection->id, 'external_id' => $externalId], ['payload' => $payload, 'status' => 'received']);
    }
}

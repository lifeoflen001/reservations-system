<?php

namespace App\Services;

use App\Jobs\SyncBookingChannel;
use App\Models\ChannelConnection;

class ChannelSyncService
{
    public function queue(ChannelConnection $connection, string $direction = 'pull'): void
    {
        if (! $connection->is_active || $connection->status !== 'configured') {
            return;
        }
        SyncBookingChannel::dispatch($connection->getKey(), $direction);
    }
}

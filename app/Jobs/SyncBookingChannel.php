<?php

namespace App\Jobs;

use App\Models\ChannelConnection;
use App\Models\IntegrationLog;
use App\Services\NullBookingChannelProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncBookingChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $connectionId, public string $direction = 'pull') {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        $connection = ChannelConnection::findOrFail($this->connectionId);
        $log = IntegrationLog::create(['integration' => 'booking_channel', 'action' => 'sync', 'entity_type' => ChannelConnection::class, 'entity_id' => $connection->id, 'direction' => $this->direction, 'status' => 'started', 'attempts' => $this->attempts()]);
        try {
            app(NullBookingChannelProvider::class)->testConnection();
            $log->update(['status' => 'succeeded', 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'completed_at' => now(), 'error_summary' => mb_substr($exception->getMessage(), 0, 1000)]);
            $connection->update(['last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
            throw $exception;
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deliveryId, public string $body, public int $timestamp) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->findOrFail($this->deliveryId);
        $started = microtime(true);
        $delivery->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1]);
        try {
            $response = Http::timeout(10)->withHeaders(['Content-Type' => 'application/json', 'X-PMS-Signature' => $delivery->signature, 'X-PMS-Timestamp' => (string) $this->timestamp, 'X-PMS-Event' => $delivery->event])->post($delivery->endpoint->url, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
            if (! $response->successful()) {
                throw new \RuntimeException('Webhook endpoint returned HTTP '.$response->status().'.');
            }
            $delivery->update(['status' => 'delivered', 'http_status' => $response->status(), 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'delivered_at' => now(), 'error_summary' => null]);
            $delivery->endpoint->update(['last_success_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'http_status' => isset($response) ? $response->status() : null, 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'error_summary' => mb_substr($exception->getMessage(), 0, 500)]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);
        $delivery?->update(['status' => 'failed', 'failed_at' => now(), 'error_summary' => mb_substr($exception->getMessage(), 0, 500)]);
        $delivery?->endpoint?->update(['last_failure_at' => now(), 'last_error' => mb_substr($exception->getMessage(), 0, 500)]);
    }
}

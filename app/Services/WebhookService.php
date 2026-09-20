<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class WebhookService
{
    public function __construct(private readonly WebhookSignatureService $signatures) {}

    public function queue(string $event, array $payload, ?string $entityType = null, ?int $entityId = null): void
    {
        foreach (WebhookEndpoint::query()->where('is_active', true)->get() as $endpoint) {
            if (! in_array($event, $endpoint->events ?? [], true)) {
                continue;
            }
            $body = json_encode(['event' => $event, 'data' => $payload], JSON_THROW_ON_ERROR);
            $timestamp = time();
            $delivery = WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => $event, 'entity_type' => $entityType, 'entity_id' => $entityId, 'payload' => ['event' => $event, 'data' => $payload], 'signature' => $this->signatures->sign($body, $endpoint->signing_secret, $timestamp), 'status' => 'queued']);
            DeliverWebhook::dispatch($delivery->id, $body, $timestamp);
        }
    }
}

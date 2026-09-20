<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use App\Models\WebhookInboundEvent;
use App\Services\WebhookSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function inbound(Request $request, string $provider, string $type, WebhookSignatureService $signatures): JsonResponse
    {
        $raw = $request->getContent();
        $integration = IntegrationSetting::query()->where('key', 'webhook:'.$provider)->first();
        $secret = $integration?->secrets['signing_secret'] ?? null;
        if (! $secret || ! $signatures->verify($raw, $secret, (string) $request->header('X-Provider-Signature'), (string) $request->header('X-Provider-Timestamp'))) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }
        $eventId = $request->header('X-Provider-Event-Id') ?: hash('sha256', $raw);
        $event = WebhookInboundEvent::query()->firstOrCreate(['provider' => $provider, 'event_id' => $eventId], ['event_type' => $type, 'status' => 'received']);
        if ($event->wasRecentlyCreated) {
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        }

        return response()->json(['accepted' => true, 'duplicate' => ! $event->wasRecentlyCreated]);
    }
}

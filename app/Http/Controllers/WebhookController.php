<?php

namespace App\Http\Controllers;

use App\Models\WebhookInboundEvent;
use App\Models\IntegrationSetting;
use App\Services\Tenancy\TenantContext;
use App\Services\WebhookSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function inbound(Request $request, string $provider, string $type, WebhookSignatureService $signatures): JsonResponse
    {
        $raw = $request->getContent();
        $matches = IntegrationSetting::query()
            ->withoutGlobalScope('tenant-ownership')
            ->where('key', 'webhook:'.$provider)
            ->where('is_enabled', true)
            ->get()
            ->filter(function (IntegrationSetting $integration) use ($raw, $request, $signatures): bool {
                $secret = $integration->secrets['signing_secret'] ?? null;
                return is_string($secret) && $secret !== '' && $signatures->verify(
                    $raw,
                    $secret,
                    (string) $request->header('X-Provider-Signature'),
                    (string) $request->header('X-Provider-Timestamp'),
                );
            });

        // The signature identifies the tenant. A provider-only URL must never
        // select the first tenant's secret or accept an ambiguous shared one.
        if ($matches->count() !== 1) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }
        $integration = $matches->first();
        app(TenantContext::class)->activate((int) $integration->organization_id, $integration->property_id ? (int) $integration->property_id : null);

        $eventId = $request->header('X-Provider-Event-Id') ?: hash('sha256', $raw);
        try {
            $event = WebhookInboundEvent::query()->firstOrCreate(['provider' => $provider, 'event_id' => $eventId], ['event_type' => $type, 'status' => 'received']);
            if ($event->wasRecentlyCreated) {
                $event->update(['status' => 'processed', 'processed_at' => now()]);
            }

            return response()->json(['accepted' => true, 'duplicate' => ! $event->wasRecentlyCreated]);
        } finally {
            app(TenantContext::class)->release();
        }
    }
}

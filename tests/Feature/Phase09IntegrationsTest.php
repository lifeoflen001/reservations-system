<?php

namespace Tests\Feature;

use App\Contracts\BookingChannelInterface;
use App\Exceptions\ProviderNotConfiguredException;
use App\Jobs\SendHotelEmail;
use App\Models\ChannelConnection;
use App\Models\IntegrationSetting;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Notifications\HotelDatabaseNotification;
use App\Services\ApiTokenService;
use App\Services\ExternalReservationService;
use App\Services\HotelEmailService;
use App\Services\SafeTemplateRenderer;
use App\Services\WebhookSignatureService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase09IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $property = Property::firstOrFail();
        app(TenantContext::class)->activate((int) $property->organization_id, (int) $property->id);
    }

    public function test_api_tokens_are_hashed_scoped_and_revocable(): void
    {
        $admin = User::firstOrFail();
        [$token, $plain] = app(ApiTokenService::class)->issue($admin, 'Rooms integration', ['rooms:read']);
        $this->assertNotSame($plain, $token->token_hash);
        $this->assertStringStartsWith(substr($plain, 0, 12), $token->token_prefix);
        $this->getJson(route('api.v1.rooms.index'))->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson(route('api.v1.rooms.index'))->assertOk()->assertJsonStructure(['data']);
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson(route('api.v1.availability', ['check_in' => '2026-10-01', 'check_out' => '2026-10-02']))->assertForbidden();
        $token->update(['revoked_at' => now()]);
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson(route('api.v1.rooms.index'))->assertUnauthorized();
    }

    public function test_email_queue_is_recorded_without_leaking_secrets(): void
    {
        $admin = User::firstOrFail();
        Queue::fake();
        IntegrationSetting::create(['key' => 'email', 'provider' => 'smtp', 'status' => 'configured', 'mode' => 'smtp', 'is_enabled' => true, 'settings' => ['host' => 'smtp.example.test', 'from_email' => 'frontdesk@example.test', 'from_name' => 'Lodgix'], 'secrets' => ['password' => 'do-not-log-this']]);
        $log = app(HotelEmailService::class)->queue('reservation_confirmation', 'guest@example.test', ['guest_name' => 'Guest', 'reservation_code' => 'WSX-0001', 'property_name' => 'Lodgix']);
        Queue::assertPushed(SendHotelEmail::class);
        $this->assertSame('queued', $log->status);
        $this->assertStringNotContainsString('do-not-log-this', json_encode($log->toArray()));
        $this->assertStringNotContainsString('do-not-log-this', (string) DB::table('integration_settings')->where('key', 'email')->value('secrets'));
        $this->actingAs($admin)->get(route('settings.index', ['section' => 'integrations']))->assertOk()->assertSee('Email / SMTP');
        $this->assertSame('Hello Guest, your reservation WSX-0001 at Lodgix is confirmed.', app(SafeTemplateRenderer::class)->render('Hello {{ guest_name }}, your reservation {{ reservation_code }} at {{ property_name }} is confirmed.', ['guest_name' => 'Guest', 'reservation_code' => 'WSX-0001', 'property_name' => 'Lodgix']));
    }

    public function test_signed_inbound_webhook_is_idempotent_and_notifications_are_scoped(): void
    {
        $admin = User::firstOrFail();
        $raw = json_encode(['event' => 'message', 'id' => 'evt-1']);
        $secret = 'webhook-secret-12345';
        IntegrationSetting::create(['key' => 'webhook:whatsapp', 'provider' => 'whatsapp', 'status' => 'configured', 'is_enabled' => true, 'secrets' => ['signing_secret' => $secret]]);
        $timestamp = (string) time();
        $signature = app(WebhookSignatureService::class)->sign($raw, $secret, (int) $timestamp);
        $this->call('POST', '/webhooks/whatsapp/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PROVIDER_SIGNATURE' => $signature, 'HTTP_X_PROVIDER_TIMESTAMP' => $timestamp, 'HTTP_X_PROVIDER_EVENT_ID' => 'evt-1'], $raw)->assertOk()->assertJson(['duplicate' => false]);
        $this->call('POST', '/webhooks/whatsapp/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PROVIDER_SIGNATURE' => $signature, 'HTTP_X_PROVIDER_TIMESTAMP' => $timestamp, 'HTTP_X_PROVIDER_EVENT_ID' => 'evt-1'], $raw)->assertOk()->assertJson(['duplicate' => true]);
        $this->assertDatabaseCount('webhook_inbound_events', 1);
        $this->assertDatabaseMissing('webhook_inbound_events', ['event_id' => 'bad']);
        $admin->notify(new HotelDatabaseNotification(['title' => 'Integration ready', 'message' => 'Signed webhook accepted.', 'category' => 'integrations', 'severity' => 'info']));
        $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->assertSee('Integration ready');
        $notification = $admin->unreadNotifications()->first();
        $this->actingAs($admin)->post(route('notifications.read', $notification->id))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_gateway_callbacks_resolve_the_signed_property_and_keep_event_ids_tenant_scoped(): void
    {
        $propertyA = Property::firstOrFail();
        $propertyBOrganization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Gateway Isolation Org', 'slug' => 'gateway-isolation-org']);
        $propertyB = Property::create([
            'organization_id' => $propertyBOrganization->id,
            'uuid' => fake()->uuid(),
            'slug' => 'gateway-isolation-property',
            'name' => 'Gateway Isolation Property',
            'property_code' => 'GATEWAY-01',
            'status' => 'active',
        ]);

        app(TenantContext::class)->activate((int) $propertyA->organization_id, (int) $propertyA->id);
        IntegrationSetting::create([
            'key' => 'gateway:demo', 'provider' => 'demo', 'status' => 'configured', 'is_enabled' => true,
            'secrets' => ['signing_secret' => 'gateway-secret-a'],
        ]);
        app(TenantContext::class)->activate((int) $propertyBOrganization->id, (int) $propertyB->id);
        IntegrationSetting::create([
            'key' => 'gateway:demo', 'provider' => 'demo', 'status' => 'configured', 'is_enabled' => true,
            'secrets' => ['signing_secret' => 'gateway-secret-b'],
        ]);

        $raw = json_encode(['id' => 'same-external-event', 'amount' => 0, 'currency' => 'USD', 'status' => 'received'], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = app(WebhookSignatureService::class)->sign($raw, 'gateway-secret-b', (int) $timestamp);

        $this->call('POST', '/webhooks/payments/demo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GATEWAY_SIGNATURE' => $signature,
            'HTTP_X_GATEWAY_TIMESTAMP' => $timestamp,
        ], $raw)->assertOk()->assertJson(['accepted' => true]);

        $this->assertDatabaseHas('gateway_transactions', ['property_id' => $propertyB->id, 'provider' => 'demo', 'external_transaction_id' => 'same-external-event']);
        $this->assertDatabaseMissing('gateway_transactions', ['property_id' => $propertyA->id, 'external_transaction_id' => 'same-external-event']);
    }

    public function test_channel_external_reservation_import_is_idempotent_and_adapters_are_safe_by_default(): void
    {
        $connection = ChannelConnection::create(['provider' => 'booking-com', 'name' => 'Demo channel', 'status' => 'not_configured', 'is_active' => false]);
        $first = app(ExternalReservationService::class)->record($connection, 'external-1', ['guest' => 'Example']);
        $second = app(ExternalReservationService::class)->record($connection, 'external-1', ['guest' => 'Updated']);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('external_reservations', 1);
        $this->expectException(ProviderNotConfiguredException::class);
        app(BookingChannelInterface::class)->testConnection();
    }
}

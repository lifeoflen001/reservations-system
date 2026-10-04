<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\IntegrationSettingsService;
use App\Services\Tenancy\TenantContext;
use App\Services\TenantOwnershipConsistencyService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SaasIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_queries_bindings_and_exports_remain_inside_the_active_property(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $propertyA = Property::firstOrFail();
        $propertyB = $this->propertyInOtherOrganization();
        $context = app(TenantContext::class);

        $context->activate((int) $propertyB->organization_id, (int) $propertyB->id);
        $clientB = Client::create(['first_name' => 'Tenant B', 'last_name' => 'Guest', 'email' => 'tenant-b@example.test']);
        $roomTypeB = RoomType::create(['name' => 'Isolation Type A', 'capacity' => 2, 'base_rate' => 120, 'is_active' => true]);
        $roomCategoryB = RoomCategory::create(['name' => 'Isolation Category A', 'is_active' => true]);
        $roomB = Room::create(['room_number' => 'B-001', 'room_category_id' => $roomCategoryB->id, 'room_type_id' => $roomTypeB->id, 'base_rate' => 120, 'capacity' => 2, 'is_active' => true]);
        $reservationB = Reservation::create([
            'code' => 'TENANT-B-001', 'client_id' => $clientB->id, 'room_id' => $roomB->id,
            'check_in' => now()->addDay(), 'check_out' => now()->addDays(2), 'adults' => 1,
            'children' => 0, 'nightly_rate' => 120, 'total_amount' => 120,
            'status' => ReservationStatus::Pending,
        ]);

        $context->activate((int) $propertyA->organization_id, (int) $propertyA->id);
        $this->assertFalse(Room::query()->whereKey($roomB)->exists());
        $this->assertFalse(Client::query()->whereKey($clientB)->exists());
        $this->assertFalse(Reservation::query()->whereKey($reservationB)->exists());
        $this->assertFalse(app(TenantOwnershipConsistencyService::class)->owns($reservationB));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $reservationB));

        $this->actingAs($admin)
            ->withSession([
                TenantContext::ORGANIZATION_SESSION_KEY => $propertyA->organization_id,
                TenantContext::PROPERTY_SESSION_KEY => $propertyA->id,
            ])
            ->get(route('reservations.show', $reservationB->id))
            ->assertNotFound();

        $response = $this->get(route('data-transfer.export', ['resource' => 'clients', 'format' => 'csv']));
        $response->assertOk();
        $this->assertStringNotContainsString('tenant-b@example.test', $response->streamedContent());
    }

    public function test_api_token_and_notification_reads_are_tenant_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $propertyA = Property::firstOrFail();
        $propertyB = $this->propertyInOtherOrganization();
        $context = app(TenantContext::class);

        $context->activate((int) $propertyB->organization_id, (int) $propertyB->id);
        $clientB = Client::create(['first_name' => 'API Tenant B', 'last_name' => 'Guest', 'email' => 'api-b@example.test']);
        $roomTypeB = RoomType::create(['name' => 'Isolation Type B', 'capacity' => 2, 'base_rate' => 130, 'is_active' => true]);
        $roomCategoryB = RoomCategory::create(['name' => 'Isolation Category B', 'is_active' => true]);
        $roomB = Room::create(['room_number' => 'B-002', 'room_category_id' => $roomCategoryB->id, 'room_type_id' => $roomTypeB->id, 'base_rate' => 130, 'capacity' => 2, 'is_active' => true]);
        $reservationB = Reservation::create([
            'code' => 'API-TENANT-B-001', 'client_id' => $clientB->id, 'room_id' => $roomB->id,
            'check_in' => now()->addDay(), 'check_out' => now()->addDays(2), 'adults' => 1,
            'children' => 0, 'nightly_rate' => 130, 'total_amount' => 130,
            'status' => ReservationStatus::Pending,
        ]);

        $context->activate((int) $propertyA->organization_id, (int) $propertyA->id);
        [, $plain] = app(ApiTokenService::class)->issue($admin, 'Tenant A isolation', ['*']);
        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson(route('api.v1.reservations.show', $reservationB->id))
            ->assertNotFound();

        DB::table('notifications')->insert([
            'id' => (string) str()->uuid(), 'type' => 'test', 'notifiable_type' => User::class,
            'notifiable_id' => $admin->id, 'data' => json_encode(['title' => 'Tenant B secret', 'category' => 'operational']),
            'organization_id' => $propertyB->organization_id, 'property_id' => $propertyB->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->withSession([
            TenantContext::ORGANIZATION_SESSION_KEY => $propertyA->organization_id,
            TenantContext::PROPERTY_SESSION_KEY => $propertyA->id,
        ])->get(route('notifications.index'))->assertOk()->assertDontSee('Tenant B secret');
    }

    public function test_integration_cache_keys_change_with_tenant_context(): void
    {
        $this->seed(DatabaseSeeder::class);
        $propertyA = Property::firstOrFail();
        $propertyB = $this->propertyInOtherOrganization();
        $context = app(TenantContext::class);
        $service = app(IntegrationSettingsService::class);

        $context->activate((int) $propertyA->organization_id, (int) $propertyA->id);
        $service->save('tenant-test', ['provider' => 'a', 'status' => 'configured', 'is_enabled' => true]);
        $this->assertSame('a', $service->get('tenant-test')?->provider);

        $context->activate((int) $propertyB->organization_id, (int) $propertyB->id);
        $service->save('tenant-test', ['provider' => 'b', 'status' => 'configured', 'is_enabled' => true]);
        $this->assertSame('b', $service->get('tenant-test')?->provider);

        $context->activate((int) $propertyA->organization_id, (int) $propertyA->id);
        $this->assertSame('a', $service->get('tenant-test')?->provider);
    }

    private function propertyInOtherOrganization(): Property
    {
        $organization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Isolation Org', 'slug' => 'isolation-org']);
        return Property::create([
            'organization_id' => $organization->id, 'name' => 'Isolation Property', 'uuid' => fake()->uuid(),
            'slug' => 'isolation-property', 'property_code' => 'ISO-001', 'status' => 'active',
        ]);
    }
}

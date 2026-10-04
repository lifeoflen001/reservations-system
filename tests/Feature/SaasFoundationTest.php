<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Plan;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SaasDefaultTenantBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaasFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_tenant_backfill_is_safe_and_idempotent(): void
    {
        $role = Role::create([
            'name' => 'manager',
            'label' => 'Manager',
            'is_system' => false,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $organization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Harbour Organization', 'slug' => 'harbour-organization']);
        $property = Property::create(['organization_id' => $organization->id, 'name' => 'Harbour Hotel']);

        $service = app(SaasDefaultTenantBackfillService::class);
        $first = $service->run();
        $second = $service->run();

        $organization = Organization::query()->where('id', $property->fresh()->organization_id)->firstOrFail();
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertFalse($first['organization_created']);
        $this->assertSame(0, $second['memberships_created']);
        $this->assertNotNull($property->fresh()->uuid);
        $this->assertSame('harbour-hotel', $property->fresh()->slug);
        $this->assertSame('PROP-'.$property->id, $property->fresh()->property_code);
        $this->assertSame($role->id, $membership->role_id);
        $this->assertDatabaseHas('property_memberships', [
            'membership_id' => $membership->id,
            'property_id' => $property->id,
            'status' => 'active',
        ]);
        $this->assertSame([
            'properties_without_organization' => 0,
            'properties_without_uuid' => 0,
            'memberships_without_role' => 0,
            'cross_organization_property_access' => 0,
        ], $service->validate());
    }

    public function test_platform_user_can_have_memberships_in_multiple_organizations(): void
    {
        $user = User::factory()->create();
        $first = Organization::create(['uuid' => fake()->uuid(), 'name' => 'First', 'slug' => 'first']);
        $second = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Second', 'slug' => 'second']);

        $firstMembership = OrganizationMembership::create(['organization_id' => $first->id, 'user_id' => $user->id]);
        $secondMembership = OrganizationMembership::create(['organization_id' => $second->id, 'user_id' => $user->id]);

        $this->assertCount(2, $user->fresh()->organizationMemberships);
        $this->assertSame($first->id, $firstMembership->organization_id);
        $this->assertSame($second->id, $secondMembership->organization_id);
    }

    public function test_property_access_cannot_cross_organization_boundaries(): void
    {
        $user = User::factory()->create();
        $first = Organization::create(['uuid' => fake()->uuid(), 'name' => 'First', 'slug' => 'first']);
        $second = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Second', 'slug' => 'second']);
        $property = Property::create(['organization_id' => $second->id, 'name' => 'Second Property']);
        $membership = OrganizationMembership::create(['organization_id' => $first->id, 'user_id' => $user->id]);

        $this->expectException(ValidationException::class);
        PropertyMembership::create(['membership_id' => $membership->id, 'property_id' => $property->id]);
    }

    public function test_subscription_skeleton_is_separate_from_website_pricing(): void
    {
        $organization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Acme', 'slug' => 'acme']);
        $plan = Plan::create(['uuid' => fake()->uuid(), 'code' => 'starter', 'name' => 'Starter']);
        $subscription = Subscription::create(['organization_id' => $organization->id, 'plan_id' => $plan->id, 'status' => 'trialing']);

        $this->assertTrue($subscription->hasStatus('trialing'));
        $this->assertSame($organization->id, $plan->subscriptions()->first()->organization_id);
        $this->assertFalse(Schema::hasColumn('website_pricing_plans', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('users', 'role_id'));
    }
}

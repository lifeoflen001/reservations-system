<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\PropertySettingsService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasTenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_property_context_is_resolved_and_namespaced_in_session(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $this->assertNotNull(session(TenantContext::ORGANIZATION_SESSION_KEY));
        $this->assertNotNull(session(TenantContext::PROPERTY_SESSION_KEY));
        $this->assertSame(Property::firstOrFail()->id, app(TenantContext::class)->propertyId());
    }

    public function test_tampered_session_values_are_replaced_with_allowed_context(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();
        $allowedProperty = Property::firstOrFail();
        $unauthorizedOrganization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Other', 'slug' => 'other']);
        $unauthorizedProperty = Property::create([
            'organization_id' => $unauthorizedOrganization->id,
            'name' => 'Other Property',
            'uuid' => fake()->uuid(),
            'slug' => 'other-property',
            'property_code' => 'OTHER-1',
        ]);

        $this->actingAs($admin)
            ->withSession([
                TenantContext::ORGANIZATION_SESSION_KEY => $unauthorizedOrganization->id,
                TenantContext::PROPERTY_SESSION_KEY => $unauthorizedProperty->id,
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame($allowedProperty->organization_id, session(TenantContext::ORGANIZATION_SESSION_KEY));
        $this->assertSame($allowedProperty->id, session(TenantContext::PROPERTY_SESSION_KEY));
    }

    public function test_organization_switch_invalidates_previous_property_and_selects_allowed_property(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        [$first, $firstProperty] = $this->organizationWithProperty('First', 'first-property', $user);
        [$second, $secondProperty] = $this->organizationWithProperty('Second', 'second-property', $user);
        $context = app(TenantContext::class);

        $this->actingAs($user);
        $context->resolveFor($user);
        $context->setOrganization($second);

        $this->assertSame($second->id, $context->organizationId());
        $this->assertSame($secondProperty->id, $context->propertyId());
        $this->assertNotSame($firstProperty->id, $context->propertyId());
    }

    public function test_restricted_and_cross_organization_properties_are_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        [$organization, $allowed] = $this->organizationWithProperty('First', 'first-property', $user);
        $restricted = Property::create([
            'organization_id' => $organization->id,
            'name' => 'Restricted',
            'uuid' => fake()->uuid(),
            'slug' => 'restricted-property',
            'property_code' => 'RESTRICTED-1',
        ]);
        $otherOrganization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Other', 'slug' => 'other']);
        $crossOrganization = Property::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Cross Organization',
            'uuid' => fake()->uuid(),
            'slug' => 'cross-organization',
            'property_code' => 'OTHER-1',
        ]);
        $context = app(TenantContext::class);

        $this->actingAs($user);
        $context->resolveFor($user);
        $this->assertSame($allowed->id, $context->propertyId());

        try {
            $context->setProperty($restricted);
            $this->fail('A restricted property must not be selectable.');
        } catch (AuthorizationException) {
            // Expected: the membership has no active access to this property.
        }

        try {
            $context->setProperty($crossOrganization);
            $this->fail('A property from another organization must not be selectable.');
        } catch (AuthorizationException) {
            // Expected: the property is outside the active organization.
        }
    }

    public function test_property_settings_cache_isolated_by_tenant_context(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        [$organization, $propertyA] = $this->organizationWithProperty('Settings Org', 'settings-a', $user);
        $propertyA->update(['name' => 'Property A', 'timezone' => 'UTC']);
        $propertyB = Property::create([
            'organization_id' => $organization->id,
            'name' => 'Property B',
            'uuid' => fake()->uuid(),
            'slug' => 'settings-b',
            'property_code' => 'SETTINGS-B',
            'timezone' => 'Africa/Dar_es_Salaam',
        ]);
        $membership = OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $organization->id)->firstOrFail();
        PropertyMembership::create(['membership_id' => $membership->id, 'property_id' => $propertyB->id, 'status' => 'active']);
        $context = app(TenantContext::class);
        $this->actingAs($user);
        $context->resolveFor($user);
        $settings = app(PropertySettingsService::class);

        $this->assertSame('Property A', $settings->name());
        $context->setProperty($propertyB);
        $this->assertSame('Property B', $settings->name());
        $this->assertSame('Africa/Dar_es_Salaam', $settings->timezone());
    }

    public function test_membership_and_property_access_revocation_invalidates_next_request(): void
    {
        $role = Role::create(['name' => 'super_administrator', 'label' => 'Administrator', 'is_system' => true, 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        [$organization, $property] = $this->organizationWithProperty('Revocation Org', 'revocation-property', $user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $membership = OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $organization->id)->firstOrFail();
        $membership->update(['status' => 'inactive']);
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->assertNull(session(TenantContext::ORGANIZATION_SESSION_KEY));
        $this->assertNull(session(TenantContext::PROPERTY_SESSION_KEY));

        $membership->update(['status' => 'active']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        PropertyMembership::query()->where('membership_id', $membership->id)->where('property_id', $property->id)->update(['status' => 'revoked']);
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_public_routes_and_website_cms_do_not_require_tenant_context(): void
    {
        $this->get('/')->assertOk()->assertSee('Lodgix');
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();

        $this->actingAs($admin)->get(route('website.dashboard'))->assertOk();
    }

    public function test_logout_clears_tenant_session_keys(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();

        $this->actingAs($admin)
            ->withSession([
                TenantContext::ORGANIZATION_SESSION_KEY => Organization::firstOrFail()->id,
                TenantContext::PROPERTY_SESSION_KEY => Property::firstOrFail()->id,
            ])
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertNull(session(TenantContext::ORGANIZATION_SESSION_KEY));
        $this->assertNull(session(TenantContext::PROPERTY_SESSION_KEY));
    }

    /** @return array{0: Organization, 1: Property} */
    private function organizationWithProperty(string $name, string $slug, User $user): array
    {
        $organization = Organization::create(['uuid' => fake()->uuid(), 'name' => $name, 'slug' => $slug]);
        $property = Property::create([
            'organization_id' => $organization->id,
            'name' => $name.' Hotel',
            'uuid' => fake()->uuid(),
            'slug' => $slug,
            'property_code' => strtoupper(str_replace('-', '_', $slug)),
        ]);
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        PropertyMembership::create(['membership_id' => $membership->id, 'property_id' => $property->id, 'status' => 'active']);

        return [$organization, $property];
    }
}

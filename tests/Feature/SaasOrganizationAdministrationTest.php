<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy\MembershipAccessService;
use App\Services\Tenancy\OrganizationOwnershipService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaasOrganizationAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_settings_are_current_org_scoped_and_audited(): void
    {
        [$admin, $organization, $property] = $this->fixture();
        $this->actingAs($admin);

        $this->get(route('settings.organization.index'))->assertOk()->assertSee('Organization settings')->assertSee($organization->name);
        $this->put(route('settings.organization.update'), [
            'name' => 'Alpha Hospitality Group',
            'billing_email' => 'billing@alpha.example',
            'country' => 'Tanzania',
            'timezone' => 'Africa/Dar_es_Salaam',
            'default_currency' => 'TZS',
        ])->assertRedirect();

        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'name' => 'Alpha Hospitality Group', 'billing_email' => 'billing@alpha.example']);
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'organization_id' => $organization->id]);
        $this->assertDatabaseHas('organization_audit_logs', ['organization_id' => $organization->id, 'action' => 'organization.updated']);
    }

    public function test_member_access_changes_are_scoped_validated_and_audited(): void
    {
        [$admin, $organization, $first, $second, $member, $membership, $foreign] = $this->fixture();
        $this->actingAs($admin);

        $this->get(route('settings.members.index'))->assertOk()->assertSee('Members &amp; access', false)->assertSee($member->email);
        $this->put(route('settings.members.update', $membership), [
            'role_id' => $membership->role_id,
            'status' => 'active',
            'property_ids' => [$first->id, $second->id],
        ])->assertRedirect(route('settings.members.index'));

        $this->assertDatabaseHas('property_memberships', ['membership_id' => $membership->id, 'property_id' => $second->id, 'status' => 'active']);
        $this->assertDatabaseHas('organization_audit_logs', ['organization_id' => $organization->id, 'action' => 'property_access.granted', 'target_user_id' => $member->id]);

        $this->put(route('settings.members.update', $membership), [
            'role_id' => $membership->role_id,
            'status' => 'active',
            'property_ids' => [$foreign->id],
        ])->assertRedirect()->assertSessionHasErrors('property_ids');
        $this->assertDatabaseMissing('property_memberships', ['membership_id' => $membership->id, 'property_id' => $foreign->id, 'status' => 'active']);

        $this->put(route('settings.members.update', $membership), [
            'role_id' => $membership->role_id,
            'status' => 'inactive',
            'property_ids' => [],
        ])->assertRedirect(route('settings.members.index'));
        $this->assertDatabaseHas('organization_memberships', ['id' => $membership->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('organization_audit_logs', ['organization_id' => $organization->id, 'action' => 'membership.updated', 'target_user_id' => $member->id]);
    }

    public function test_property_access_screen_reuses_central_access_service(): void
    {
        [$admin, $organization, $first, $second, $member, $membership] = $this->fixture();
        $this->actingAs($admin);

        $this->get(route('settings.properties.access', $first))->assertOk()->assertSee($first->name)->assertSee($member->name);
        $this->put(route('settings.properties.access.update', $first), ['membership_ids' => [$membership->id]])->assertRedirect(route('settings.properties.access', $first));
        $this->assertDatabaseHas('property_memberships', ['membership_id' => $membership->id, 'property_id' => $first->id, 'status' => 'active']);

        $this->put(route('settings.properties.access.update', $first), ['membership_ids' => []])->assertRedirect();
        $this->assertDatabaseHas('property_memberships', ['membership_id' => $membership->id, 'property_id' => $first->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('organization_audit_logs', ['organization_id' => $organization->id, 'action' => 'property_access.revoked']);
        $this->assertDatabaseMissing('property_memberships', ['membership_id' => $membership->id, 'property_id' => $second->id, 'status' => 'active']);
    }

    public function test_membership_deactivation_preserves_history_and_next_context_request_falls_back(): void
    {
        [$admin, $organization, $first, $second] = $this->fixture();
        $this->actingAs($admin);
        app(TenantContext::class)->activate($organization->id, $second->id);
        $membership = OrganizationMembership::where('organization_id', $organization->id)->where('user_id', $admin->id)->firstOrFail();

        app(MembershipAccessService::class)->updateMembership($membership, $admin, $membership->role_id, 'active', [$first->id]);
        app(TenantContext::class)->release();
        app(TenantContext::class)->resolveFor($admin);

        $this->assertSame($first->id, app(TenantContext::class)->propertyId());
        $this->assertDatabaseHas('property_memberships', ['membership_id' => $membership->id, 'property_id' => $second->id, 'status' => 'inactive']);
    }

    public function test_owner_backfill_selects_highest_authority_and_audits_selection(): void
    {
        $administratorRole = Role::firstOrCreate(['name' => 'administrator'], ['label' => 'Administrator', 'is_system' => true, 'is_active' => true]);
        $managerRole = Role::firstOrCreate(['name' => 'manager'], ['label' => 'Manager', 'is_system' => true, 'is_active' => true]);
        $administrator = User::factory()->create(['role_id' => $administratorRole->id, 'is_active' => true]);
        $manager = User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);
        $organization = Organization::create(['uuid' => fake()->uuid(), 'name' => 'Backfill Org', 'slug' => 'backfill-'.uniqid()]);
        $adminMembership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $administrator->id, 'role_id' => $administratorRole->id, 'status' => 'active']);
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $manager->id, 'role_id' => $managerRole->id, 'status' => 'active']);

        $report = app(OrganizationOwnershipService::class)->backfill();

        $this->assertSame(1, $report['owners_granted']);
        $this->assertTrue((bool) $adminMembership->fresh()->is_owner);
        $this->assertDatabaseHas('organization_audit_logs', ['organization_id' => $organization->id, 'action' => 'organization.owner_granted', 'target_user_id' => $administrator->id]);
        $this->assertSame('administrator', $report['selections'][0]['role']);
    }

    public function test_owner_grant_remove_and_last_owner_protection_are_tenant_scoped(): void
    {
        [$admin, $organization, $first, $second, $member, $membership, $foreign] = $this->fixture();
        OrganizationMembership::where('id', $membership->id)->update(['is_owner' => false]);
        OrganizationMembership::where('organization_id', $organization->id)->where('user_id', $admin->id)->update(['is_owner' => true]);
        $membership->refresh();
        $adminMembership = OrganizationMembership::where('organization_id', $organization->id)->where('user_id', $admin->id)->firstOrFail();
        $this->actingAs($admin);

        $this->post(route('settings.members.owner.grant', $membership))->assertRedirect();
        $this->assertTrue((bool) $membership->fresh()->is_owner);
        $this->assertDatabaseHas('organization_audit_logs', ['action' => 'organization.owner_granted', 'target_user_id' => $member->id]);

        $this->delete(route('settings.members.owner.remove', $membership))->assertRedirect();
        $this->assertFalse((bool) $membership->fresh()->is_owner);

        $this->delete(route('settings.members.owner.remove', $adminMembership))->assertRedirect()->assertSessionHasErrors('membership');
        $this->put(route('settings.members.update', $adminMembership), ['role_id' => $adminMembership->role_id, 'status' => 'inactive', 'property_ids' => [$first->id]])->assertRedirect()->assertSessionHasErrors('status');
        $this->assertTrue((bool) $adminMembership->fresh()->is_owner);

        $managerUser = User::factory()->create(['role_id' => $adminMembership->role_id, 'is_active' => true]);
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $managerUser->id, 'role_id' => $adminMembership->role_id, 'status' => 'active']);
        $this->actingAs($managerUser);
        app(\App\Services\Tenancy\TenantContext::class)->activate($organization->id, $first->id);
        $this->put(route('settings.members.update', $adminMembership), ['role_id' => $adminMembership->role_id, 'status' => 'inactive', 'property_ids' => [$first->id]])->assertRedirect()->assertSessionHasErrors('status');
        $this->assertTrue((bool) $adminMembership->fresh()->is_owner);

        $foreignUser = User::factory()->create(['role_id' => $adminMembership->role_id, 'is_active' => true]);
        $foreignMembership = OrganizationMembership::create(['organization_id' => $foreign->organization_id, 'user_id' => $foreignUser->id, 'role_id' => $adminMembership->role_id, 'is_owner' => true, 'status' => 'active']);
        $this->delete(route('settings.members.owner.remove', $foreignMembership))->assertForbidden();
    }

    public function test_non_owner_cannot_grant_ownership(): void
    {
        [$admin, $organization, $first, $second, $member, $membership] = $this->fixture();
        OrganizationMembership::where('organization_id', $organization->id)->update(['is_owner' => false]);
        $this->actingAs($member);

        $this->post(route('settings.members.owner.grant', $membership))->assertForbidden();
        $this->assertFalse((bool) $membership->fresh()->is_owner);
    }

    /** @return array<int, mixed> */
    private function fixture(): array
    {
        $role = Role::firstOrCreate(['name' => 'super_administrator'], ['label' => 'Super Administrator', 'is_system' => true, 'is_active' => true]);
        $memberRole = Role::firstOrCreate(['name' => 'front_desk'], ['label' => 'Front Office', 'is_system' => true, 'is_active' => true]);
        $admin = User::create(['name' => 'Alpha Owner', 'email' => 'owner-'.uniqid().'@alpha.example', 'username' => 'owner-'.uniqid(), 'password' => Hash::make('secret'), 'role_id' => $role->id, 'is_active' => true]);
        $member = User::create(['name' => 'Alpha Restricted', 'email' => 'member-'.uniqid().'@alpha.example', 'username' => 'member-'.uniqid(), 'password' => Hash::make('secret'), 'role_id' => $memberRole->id, 'is_active' => true]);
        $organization = Organization::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Alpha Hospitality', 'slug' => 'alpha-'.uniqid(), 'status' => 'active']);
        $first = Property::create(['organization_id' => $organization->id, 'name' => 'Alpha One', 'slug' => 'alpha-one-'.uniqid(), 'property_code' => 'ALP-01', 'status' => 'active', 'default_language' => 'en', 'timezone' => 'Africa/Dar_es_Salaam']);
        $second = Property::create(['organization_id' => $organization->id, 'name' => 'Alpha Two', 'slug' => 'alpha-two-'.uniqid(), 'property_code' => 'ALP-02', 'status' => 'active', 'default_language' => 'en', 'timezone' => 'Africa/Dar_es_Salaam']);
        $adminMembership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => $role->id, 'is_owner' => true, 'status' => 'active']);
        $membership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'role_id' => $memberRole->id, 'status' => 'active']);
        PropertyMembership::create(['membership_id' => $adminMembership->id, 'property_id' => $first->id, 'status' => 'active']);
        PropertyMembership::create(['membership_id' => $adminMembership->id, 'property_id' => $second->id, 'status' => 'active']);
        PropertyMembership::create(['membership_id' => $membership->id, 'property_id' => $first->id, 'status' => 'active']);

        $foreignOrganization = Organization::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Beta Hospitality', 'slug' => 'beta-'.uniqid(), 'status' => 'active']);
        $foreign = Property::create(['organization_id' => $foreignOrganization->id, 'name' => 'Beta One', 'slug' => 'beta-one-'.uniqid(), 'property_code' => 'BET-01', 'status' => 'active', 'default_language' => 'en', 'timezone' => 'Africa/Dar_es_Salaam']);

        $this->actingAs($admin);
        app(TenantContext::class)->activate($organization->id, $first->id);

        return [$admin, $organization, $first, $second, $member, $membership, $foreign];
    }
}

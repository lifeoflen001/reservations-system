<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaasMultiPropertyUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_property_switch_is_post_only_and_clears_sensitive_session_state(): void
    {
        [$user, $organization, $first, $second] = $this->tenantFixture();
        $this->actingAs($user);
        session(['pos.cart' => ['sku' => 'COFFEE001'], 'finance.account_id' => 99, 'tenant.property_id' => $first->id]);

        $this->get('/context/property?property_id='.$second->id)->assertStatus(405);
        $this->post(route('context.property'), ['property_id' => $second->id, 'return_to' => route('dashboard')])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($second->id, session('tenant.property_id'));
        $this->assertNull(session('pos.cart'));
        $this->assertNull(session('finance.account_id'));
    }

    public function test_unauthorized_and_cross_organization_properties_cannot_be_selected(): void
    {
        [$user, $organization, $first] = $this->tenantFixture();
        $otherOrganization = Organization::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Other Org', 'slug' => 'other-org', 'status' => 'active']);
        $foreign = Property::create(['organization_id' => $otherOrganization->id, 'name' => 'Foreign', 'slug' => 'foreign', 'property_code' => 'FOR-01', 'status' => 'active', 'default_language' => 'en', 'timezone' => 'Africa/Dar_es_Salaam']);
        $this->actingAs($user);

        $this->post(route('context.property'), ['property_id' => $foreign->id, 'return_to' => route('dashboard')])
            ->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->assertSame($first->id, session('tenant.property_id'));
    }

    public function test_organization_switch_resets_to_an_authorized_property(): void
    {
        [$user, $organization] = $this->tenantFixture();
        $other = Organization::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'East Africa Lodges', 'slug' => 'east-africa-lodges', 'status' => 'active']);
        $property = Property::create(['organization_id' => $other->id, 'name' => 'Zanzibar Resort', 'slug' => 'zanzibar-resort', 'property_code' => 'ZAN-01', 'status' => 'active', 'default_language' => 'en', 'timezone' => 'Africa/Dar_es_Salaam']);
        $membership = OrganizationMembership::create(['organization_id' => $other->id, 'user_id' => $user->id, 'role_id' => $user->role_id, 'status' => 'active']);
        PropertyMembership::create(['membership_id' => $membership->id, 'property_id' => $property->id, 'status' => 'active']);
        $this->actingAs($user);

        $this->post(route('context.organization'), ['organization_id' => $other->id, 'return_to' => route('dashboard')])->assertRedirect(route('dashboard'));
        $this->assertSame($other->id, session('tenant.organization_id'));
        $this->assertSame($property->id, session('tenant.property_id'));
        $this->assertNotSame($organization->id, session('tenant.organization_id'));
    }

    public function test_property_management_creates_only_inside_current_organization_and_grants_creator_access(): void
    {
        [$user, $organization] = $this->tenantFixture();
        $this->actingAs($user);

        $this->post(route('settings.properties.store'), [
            'name' => 'Zanzibar Resort', 'property_code' => 'ZAN-01', 'timezone' => 'Africa/Dar_es_Salaam', 'base_currency_id' => \App\Models\Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true])->id,
        ])->assertRedirect(route('settings.properties.index'));

        $property = Property::where('organization_id', $organization->id)->where('property_code', 'ZAN-01')->firstOrFail();
        $membership = OrganizationMembership::where('organization_id', $organization->id)->where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('property_memberships', ['membership_id' => $membership->id, 'property_id' => $property->id, 'status' => 'active']);
        $this->assertDatabaseCount('rooms', 0);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('pos_orders', 0);
    }

    /** @return array{0: User, 1: Organization, 2: Property, 3: Property} */
    private function tenantFixture(): array
    {
        $role = Role::firstOrCreate(['name' => 'super_administrator'], ['label' => 'Super Administrator', 'is_system' => true, 'is_active' => true]);
        $user = User::create(['name' => 'Multi Property Admin', 'email' => 'multi-'.uniqid().'@example.com', 'username' => 'multi-'.uniqid(), 'password' => Hash::make('secret'), 'role_id' => $role->id, 'is_active' => true]);
        $organization = Organization::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Serengeti Hospitality Group', 'slug' => 'serengeti-'.uniqid(), 'status' => 'active']);
        $first = Property::create(['organization_id' => $organization->id, 'name' => 'Arusha Hotel', 'slug' => 'arusha-'.uniqid(), 'property_code' => 'ARU-01', 'status' => 'active', 'default_language' => 'en', 'timezone' => 'Africa/Dar_es_Salaam']);
        $second = Property::create(['organization_id' => $organization->id, 'name' => 'Serengeti Lodge', 'slug' => 'serengeti-'.uniqid(), 'property_code' => 'SER-01', 'status' => 'active', 'default_language' => 'en', 'timezone' => 'Africa/Dar_es_Salaam']);
        $membership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active']);
        PropertyMembership::insert([
            ['membership_id' => $membership->id, 'property_id' => $first->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['membership_id' => $membership->id, 'property_id' => $second->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        return [$user, $organization, $first, $second];
    }
}

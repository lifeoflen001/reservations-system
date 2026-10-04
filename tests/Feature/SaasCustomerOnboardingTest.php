<?php

namespace Tests\Feature;

use App\Mail\OrganizationInvitationMail;
use App\Models\Currency;
use App\Models\Installation;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Plan;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\OnboardingService;
use App\Services\OrganizationProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SaasCustomerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_unverified_account_without_tenant_data(): void
    {
        Installation::create(['status' => 'complete', 'completed_at' => now()]);
        Notification::fake();
        $response = $this->post(route('register.store'), [
            'name' => 'New Hotel Owner', 'email' => 'owner@example.test', 'password' => 'password',
            'password_confirmation' => 'password', 'terms' => '1', 'website' => '',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($user->email_verification_required);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('organizations', 0);
        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_onboarding_is_transactional_and_finishes_with_owner_property_and_plan(): void
    {
        $this->installReferenceData();
        $user = User::factory()->create(['email' => 'owner@example.test', 'email_verification_required' => true]);
        $onboarding = app(OrganizationProvisioningService::class)->createFor($user, ['name' => 'Acacia Hotels', 'country' => 'TZ', 'timezone' => 'Africa/Dar_es_Salaam', 'currency' => 'USD']);
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();
        app(OnboardingService::class)->selectPlan($user, $plan->getKey());
        $property = app(OnboardingService::class)->createProperty($user, ['name' => 'Acacia Main', 'property_code' => 'ACACIA-001', 'country' => 'TZ', 'timezone' => 'Africa/Dar_es_Salaam', 'currency' => 'USD', 'email' => $user->email]);
        app(OnboardingService::class)->finish($user);

        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $onboarding->organization_id, 'user_id' => $user->id, 'is_owner' => true, 'status' => 'active']);
        $this->assertDatabaseHas('subscriptions', ['organization_id' => $onboarding->organization_id, 'plan_id' => $plan->id, 'status' => 'trialing']);
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'organization_id' => $onboarding->organization_id]);
        $this->assertDatabaseHas('property_memberships', ['property_id' => $property->id]);
        $this->assertDatabaseHas('organization_onboardings', ['organization_id' => $onboarding->organization_id, 'completed_at' => $onboarding->fresh()->completed_at]);
    }

    public function test_invitation_uses_hashed_single_use_token_and_acceptance_rechecks_access(): void
    {
        $this->installReferenceData();
        Mail::fake();
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $member = User::factory()->create(['email' => 'member@example.test']);
        $organization = Organization::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Acacia Hotels', 'slug' => 'acacia-hotels', 'status' => 'active']);
        $role = Role::query()->where('name', 'manager')->firstOrFail();
        $property = Property::create(['organization_id' => $organization->id, 'uuid' => (string) \Illuminate\Support\Str::uuid(), 'slug' => 'acacia-main', 'property_code' => 'ACACIA-001', 'name' => 'Acacia Main', 'status' => 'active', 'base_currency_id' => Currency::query()->where('code', 'USD')->value('id')]);
        $ownerMembership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role_id' => $role->id, 'is_owner' => true, 'status' => 'active']);
        PropertyMembership::create(['membership_id' => $ownerMembership->id, 'property_id' => $property->id, 'status' => 'active']);
        $legacy = Plan::query()->where('code', 'legacy_full_access')->firstOrFail();
        Subscription::create(['organization_id' => $organization->id, 'plan_id' => $legacy->id, 'status' => 'active']);

        app(InvitationService::class)->create($owner, $organization, $member->email, $role->id, [$property->id]);
        Mail::assertSent(OrganizationInvitationMail::class, function (OrganizationInvitationMail $mail) use ($member, $organization): bool {
            $this->assertNotSame($mail->token, OrganizationInvitation::query()->where('organization_id', $organization->id)->value('token_hash'));
            app(InvitationService::class)->accept($member, $mail->token);
            return true;
        });
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active', 'is_owner' => false]);
        $this->assertDatabaseHas('organization_invitations', ['organization_id' => $organization->id, 'email' => $member->email, 'status' => 'accepted']);
    }

    private function installReferenceData(): void
    {
        Installation::create(['status' => 'complete', 'completed_at' => now()]);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        Role::firstOrCreate(['name' => 'administrator'], ['label' => 'Administrator', 'is_active' => true]);
        Role::firstOrCreate(['name' => 'manager'], ['label' => 'Manager', 'is_active' => true]);
        $plan = Plan::firstOrCreate(['code' => 'starter'], ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'slug' => 'starter', 'name' => 'Starter', 'status' => 'active', 'is_public' => true, 'is_system' => false, 'is_onboarding_eligible' => true, 'is_onboarding_default' => true]);
        $plan->forceFill(['is_public' => true, 'is_onboarding_eligible' => true, 'is_system' => false])->save();
        $plan->limits()->updateOrCreate(['key' => 'properties'], ['value' => 1]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAdministrator;
use App\Models\PlatformSupportSession;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Models\User;
use App\Services\Platform\SupportAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_identity_is_separate_from_customer_authentication(): void
    {
        $customer = User::factory()->create();
        $admin = PlatformAdministrator::factory()->create(['email' => 'control@example.test', 'password' => 'password']);

        $this->actingAs($customer)->get(route('platform.dashboard'))->assertRedirect(route('platform.login'));
        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('platform.2fa.challenge'));
        $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
        $this->post(route('platform.2fa.challenge.verify'), ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticated('platform');
        $this->assertAuthenticatedAs($customer, 'web');
        // Customer and platform sessions remain independent. The customer
        // request still follows the customer authorization rules.
        $this->get(route('dashboard'))->assertForbidden();
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'platform.2fa.challenge_verified', 'platform_administrator_id' => $admin->id]);

        $this->post(route('platform.logout'))->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
        $this->assertAuthenticatedAs($customer, 'web');
    }

    public function test_disabled_platform_administrator_cannot_login(): void
    {
        $admin = PlatformAdministrator::factory()->disabled()->create(['email' => 'disabled@example.test', 'password' => 'password']);

        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest('platform');
    }

    public function test_unenrolled_platform_administrator_is_forced_through_enrollment(): void
    {
        $admin = PlatformAdministrator::factory()->unenrolled()->create(['email' => 'enroll@example.test', 'password' => 'password']);

        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('platform.2fa.enroll'));
        $this->assertGuest('platform');
        $this->get(route('platform.2fa.enroll'))->assertOk()->assertSee('Set up two-factor authentication');

        $secret = Fortify::currentEncrypter()->decrypt($admin->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->post(route('platform.2fa.enroll.confirm'), ['code' => $code])->assertRedirect(route('platform.2fa.recovery'));
        $this->assertAuthenticated('platform');
        $this->get(route('platform.2fa.recovery'))->assertOk()->assertSee('Save your recovery codes');
        $this->assertNotSame($secret, $admin->fresh()->two_factor_secret);
    }

    public function test_enrolled_platform_administrator_requires_correct_totp(): void
    {
        $admin = PlatformAdministrator::factory()->create(['email' => 'challenge@example.test', 'password' => 'password']);

        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('platform.2fa.challenge'));
        $this->assertGuest('platform');
        $this->post(route('platform.2fa.challenge.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->post(route('platform.2fa.challenge.verify'), ['code' => $code])->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticated('platform');
    }

    public function test_recovery_code_is_one_time_and_customer_cannot_use_platform_challenge(): void
    {
        $admin = PlatformAdministrator::factory()->create(['email' => 'recovery@example.test', 'password' => 'password']);
        $recoveryCode = $admin->recoveryCodes()[0];

        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('platform.2fa.challenge.verify'), ['recovery_code' => $recoveryCode])->assertRedirect(route('platform.dashboard'));
        $this->post(route('platform.logout'));
        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('platform.2fa.challenge.verify'), ['recovery_code' => $recoveryCode])->assertSessionHasErrors('code');

        Auth::guard('platform')->logout();
        $customer = User::factory()->create();
        $this->flushSession();
        $this->actingAs($customer)->get(route('platform.2fa.challenge'))->assertRedirect(route('platform.login'));
    }

    public function test_platform_administrator_can_rotate_recovery_codes_and_self_reset_two_factor(): void
    {
        $admin = PlatformAdministrator::factory()->create(['email' => 'self-reset@example.test', 'password' => 'password']);
        $oldRecoveryCode = $admin->recoveryCodes()[0];
        $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($admin, 'platform')
            ->post(route('platform.profile.2fa.recovery-codes'), ['current_password' => 'password', 'code' => $code])
            ->assertRedirect();
        $newRecoveryCode = $admin->fresh()->recoveryCodes()[0];
        $this->assertNotSame($oldRecoveryCode, $newRecoveryCode);

        $this->actingAs($admin, 'platform')
            ->post(route('platform.profile.2fa.reset'), [
                'current_password' => 'password',
                'recovery_code' => $newRecoveryCode,
            ])
            ->assertRedirect(route('platform.login'));

        $this->assertGuest('platform');
        $this->assertNull($admin->fresh()->two_factor_secret);
    }

    public function test_platform_dashboard_uses_real_platform_counts(): void
    {
        $admin = PlatformAdministrator::factory()->create();
        $organization = Organization::create(['uuid' => (string) Str::uuid(), 'name' => 'Alpha Hotel Group', 'slug' => 'alpha-hotel-group', 'status' => 'active', 'subscription_status' => 'active']);
        Property::create(['organization_id' => $organization->id, 'uuid' => (string) Str::uuid(), 'name' => 'Alpha Hotel', 'slug' => 'alpha-hotel', 'property_code' => 'AH101', 'status' => 'active']);

        $this->actingAs($admin, 'platform')->get(route('platform.dashboard'))->assertOk()->assertSee('Alpha Hotel Group')->assertSee('Platform overview')->assertSee('1');
    }

    public function test_support_access_requires_reason_expiry_and_valid_property_scope(): void
    {
        [$admin, $organization, $property] = $this->platformFixture();

        $this->actingAs($admin, 'platform')->post(route('platform.support.start'), ['organization_id' => $organization->id, 'property_id' => $property->id, 'duration' => 15])->assertSessionHasErrors(['reason']);
        $this->actingAs($admin, 'platform')->post(route('platform.support.start'), ['organization_id' => $organization->id, 'property_id' => 999999, 'reason' => 'Investigate a staging support request', 'duration' => 15])->assertSessionHasErrors(['property_id']);
        $this->assertDatabaseCount('platform_support_sessions', 0);
    }

    public function test_support_session_is_time_bounded_audited_and_exitable(): void
    {
        [$admin, $organization, $property] = $this->platformFixture();

        $response = $this->actingAs($admin, 'platform')->post(route('platform.support.start'), ['organization_id' => $organization->id, 'property_id' => $property->id, 'reason' => 'Investigate a staging support request', 'duration' => 15]);
        $session = PlatformSupportSession::firstOrFail();
        $response->assertRedirect(route('platform.support.workspace', $session));
        $this->get(route('platform.support.workspace', $session))->assertOk()->assertSee('Support mode')->assertSee('Alpha Hotel');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'support.session_started', 'organization_id' => $organization->id, 'property_id' => $property->id]);

        $this->post(route('platform.support.end'))->assertRedirect(route('platform.support'));
        $this->assertDatabaseHas('platform_support_sessions', ['id' => $session->id, 'status' => 'ended']);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'support.session_ended', 'target_id' => (string) $session->id]);
    }

    public function test_support_workspace_requires_the_active_owned_session(): void
    {
        [$admin, $organization, $property] = $this->platformFixture();
        $session = PlatformSupportSession::create([
            'uuid' => (string) Str::uuid(),
            'platform_administrator_id' => $admin->id,
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'reason' => 'Verify scoped support access',
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->actingAs($admin, 'platform')
            ->get(route('platform.support.workspace', $session))
            ->assertForbidden();

        $otherAdmin = PlatformAdministrator::factory()->create();
        $this->actingAs($otherAdmin, 'platform')
            ->withSession([SupportAccessService::SESSION_KEY => $session->id])
            ->get(route('platform.support.workspace', $session))
            ->assertNotFound();
    }

    public function test_expired_support_workspace_is_rejected_and_audited(): void
    {
        [$admin, $organization, $property] = $this->platformFixture();
        $session = PlatformSupportSession::create([
            'uuid' => (string) Str::uuid(),
            'platform_administrator_id' => $admin->id,
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'reason' => 'Verify expiry handling',
            'status' => 'active',
            'started_at' => now()->subMinutes(20),
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin, 'platform')
            ->withSession([SupportAccessService::SESSION_KEY => $session->id])
            ->get(route('platform.support.workspace', $session))
            ->assertRedirect(route('platform.support'));

        $this->assertDatabaseHas('platform_support_sessions', ['id' => $session->id, 'status' => 'expired']);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'support.session_expired', 'target_id' => (string) $session->id]);
    }

    public function test_support_workspace_has_no_mutation_endpoint(): void
    {
        [$admin, $organization, $property] = $this->platformFixture();
        $this->actingAs($admin, 'platform')->post(route('platform.support.start'), [
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'reason' => 'Verify read-only support access',
            'duration' => 15,
        ]);
        $session = PlatformSupportSession::firstOrFail();

        $this->actingAs($admin, 'platform')
            ->withSession([SupportAccessService::SESSION_KEY => $session->id])
            ->post(route('platform.support.workspace', $session))
            ->assertStatus(405);
    }

    public function test_customer_owner_cannot_access_platform_and_platform_admin_is_not_a_member(): void
    {
        [$admin, $organization] = $this->platformFixture();
        $owner = User::factory()->create();
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'is_owner' => true, 'status' => 'active']);

        $this->actingAs($owner)->get(route('platform.dashboard'))->assertRedirect(route('platform.login'));
        $this->assertDatabaseHas('platform_administrators', ['id' => $admin->id]);
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $owner->id]);
    }

    public function test_platform_admin_self_protection_and_last_admin_safety(): void
    {
        $admin = PlatformAdministrator::factory()->create();
        $other = PlatformAdministrator::factory()->create();

        $this->actingAs($admin, 'platform')->patch(route('platform.administrators.status', $admin), ['status' => 'disabled'])->assertStatus(422);
        $this->actingAs($admin, 'platform')->patch(route('platform.administrators.status', $other), ['status' => 'disabled'])->assertRedirect();
        $this->assertDatabaseHas('platform_administrators', ['id' => $other->id, 'status' => 'disabled']);
        $this->actingAs($admin, 'platform')->patch(route('platform.administrators.status', $admin), ['status' => 'disabled'])->assertStatus(422);
    }

    /** @return array{0:PlatformAdministrator,1:Organization,2:Property} */
    private function platformFixture(): array
    {
        $admin = PlatformAdministrator::factory()->create();
        $organization = Organization::create(['uuid' => (string) Str::uuid(), 'name' => 'Alpha Hotel Group', 'slug' => 'alpha-hotel-group', 'status' => 'active', 'subscription_status' => 'active']);
        $property = Property::create(['organization_id' => $organization->id, 'uuid' => (string) Str::uuid(), 'name' => 'Alpha Hotel', 'slug' => 'alpha-hotel', 'property_code' => 'AH101', 'status' => 'active']);
        return [$admin, $organization, $property];
    }
}

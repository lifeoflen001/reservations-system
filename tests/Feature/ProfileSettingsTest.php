<?php

namespace Tests\Feature;

use App\Jobs\SendHotelEmail;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_crop_ready_profile_picture_and_remove_it(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();

        $file = UploadedFile::fake()->createWithContent('profile-picture.png', file_get_contents(base_path('public/assets/images/majesticlogo.png')));

        $this->actingAs($admin)->post(route('profile.avatar.update'), [
            '_method' => 'PUT',
            'avatar' => $file,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $avatarPath = $admin->fresh()->avatar_path;
        $this->assertNotNull($avatarPath);
        Storage::disk('public')->assertExists($avatarPath);
        $this->actingAs($admin)->get(route('profile.avatar'))->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($admin)->post(route('profile.avatar.update'), [
            '_method' => 'PUT',
            'remove_avatar' => '1',
        ])->assertRedirect();

        $this->assertNull($admin->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_profile_preferences_are_saved_for_the_current_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();

        $this->actingAs($admin)->get(route('profile', ['section' => 'preferences']))
            ->assertOk()
            ->assertSee('Two-factor authentication')
            ->assertSee('Preferences');

        $this->actingAs($admin)->put(route('profile.preferences.update'), [
            'theme' => 'dark',
            'channels' => ['email'],
            'categories' => ['financial'],
        ])->assertRedirect(route('profile', ['section' => 'preferences']));

        $this->assertSame('dark', $admin->preferences()->firstOrFail()->theme);
        $this->assertSame(['email'], $admin->notificationPreferences()->firstOrFail()->channels);
        $this->assertSame(['financial'], $admin->notificationPreferences()->firstOrFail()->categories);
    }

    public function test_user_can_change_username_from_profile_and_duplicate_usernames_are_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();

        $this->actingAs($admin)->put(route('profile.update'), [
            'first_name' => 'HotelDesk',
            'last_name' => 'Administrator',
            'username' => 'hotel-admin',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('hotel-admin', $admin->fresh()->username);
        User::factory()->create(['username' => 'admin']);

        $this->actingAs($admin)->put(route('profile.update'), [
            'first_name' => 'HotelDesk',
            'last_name' => 'Administrator',
            'username' => 'admin',
        ])->assertRedirect()->assertSessionHasErrors('username');

        $this->assertSame('hotel-admin', $admin->fresh()->username);
    }

    public function test_email_change_requires_a_queued_code_before_updating_the_email(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();
        Queue::fake();

        $this->actingAs($admin)->post(route('profile.email.request'), [
            'email' => 'new-admin@example.test',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('admin@hoteldesk.test', $admin->fresh()->email);
        $this->assertDatabaseHas('email_change_verifications', [
            'user_id' => $admin->id,
            'email' => 'new-admin@example.test',
            'attempts' => 0,
        ]);

        $verificationJob = null;
        Queue::assertPushed(SendHotelEmail::class, function (SendHotelEmail $job) use (&$verificationJob): bool {
            $verificationJob = $job;

            return $job->variables['verification_code'] !== '';
        });

        $this->actingAs($admin)->post(route('profile.email.verify'), [
            'code' => $verificationJob->variables['verification_code'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('new-admin@example.test', $admin->fresh()->email);
        $this->assertNotNull($admin->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_change_verifications', ['user_id' => $admin->id]);
    }

    public function test_invalid_email_change_codes_are_counted_and_do_not_change_the_email(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();
        Queue::fake();

        $this->actingAs($admin)->post(route('profile.email.request'), [
            'email' => 'another-admin@example.test',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('profile.email.verify'), ['code' => '000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->assertSame('admin@hoteldesk.test', $admin->fresh()->email);
        $this->assertDatabaseHas('email_change_verifications', [
            'user_id' => $admin->id,
            'attempts' => 1,
        ]);
    }

    public function test_two_factor_enrollment_and_login_challenge_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::firstOrFail();

        $this->actingAs($admin)->post(route('profile.two-factor.enable'), ['current_password' => 'Admin123!'])
            ->assertRedirect(route('profile', ['section' => 'two-factor']));

        $pending = $admin->fresh();
        $secret = Crypt::decrypt($pending->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($pending)->post(route('profile.two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('profile', ['section' => 'two-factor']));
        $this->assertTrue($pending->fresh()->hasEnabledTwoFactorAuthentication());

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['identity' => 'admin', 'password' => 'Admin123!'])
            ->assertRedirect(route('two-factor.login'));

        $google2fa = app(Google2FA::class);
        $challengeCode = $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1);
        $this->post(route('two-factor.login.store'), ['code' => $challengeCode])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin->fresh());

        $recoveryCode = $admin->fresh()->recoveryCodes()[0];
        $this->post(route('profile.two-factor.disable'), [
            'current_password' => 'Admin123!',
            'recovery_code' => $recoveryCode,
        ])->assertRedirect(route('profile', ['section' => 'two-factor']));
        $this->assertNull($admin->fresh()->two_factor_secret);
    }
}

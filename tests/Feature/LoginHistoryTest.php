<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded_and_can_be_signed_out(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'identity' => 'admin',
            'password' => 'Admin123!',
        ])->assertRedirect(route('dashboard'));

        $history = LoginHistory::query()->where('user_id', User::firstOrFail()->id)->latest('id')->firstOrFail();

        $this->assertNull($history->logged_out_at);
        $this->assertSame($this->app['session']->getId(), $history->session_id);
        $this->assertSame('Local network', $history->location);

        $this->get(route('profile', ['section' => 'login-history']))
            ->assertOk()
            ->assertSee('Login history')
            ->assertSee('Active');

        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_user_can_forget_another_active_device(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::firstOrFail();
        $this->actingAs($user);

        $other = LoginHistory::create([
            'user_id' => $user->id,
            'session_id' => 'other-device-session',
            'ip_address' => '10.0.0.8',
            'device_name' => 'Chrome on Windows',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'device_type' => 'Desktop',
            'location' => 'Local network',
            'login_at' => now()->subHour(),
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $this->post(route('profile.login-history.forget', $other))
            ->assertRedirect(route('profile', ['section' => 'login-history']));

        $this->assertNotNull($other->fresh()->logged_out_at);
    }

    public function test_user_can_sign_out_all_other_devices(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::firstOrFail();
        $this->actingAs($user);

        $histories = collect(['device-one-session', 'device-two-session'])->map(fn (string $sessionId): LoginHistory => LoginHistory::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'device_name' => 'Chrome on Windows',
            'login_at' => now()->subHour(),
            'last_seen_at' => now()->subMinutes(5),
        ]));

        $this->post(route('profile.login-history.forget-others'))
            ->assertRedirect(route('profile', ['section' => 'login-history']));

        $histories->each(fn (LoginHistory $history) => $this->assertNotNull($history->fresh()->logged_out_at));
    }
}

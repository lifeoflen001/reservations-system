<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_can_send_a_reset_link(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee('name="email"', false);

        $this->post(route('password.email'), ['email' => 'admin@hoteldesk.test'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo(User::firstOrFail(), ResetPassword::class);
    }

    public function test_valid_reset_token_updates_password_and_redirects_to_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::firstOrFail();
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Create a new password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewAdmin123!',
            'password_confirmation' => 'NewAdmin123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewAdmin123!', $user->fresh()->password));
    }
}

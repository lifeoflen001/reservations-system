<?php

namespace Database\Factories;

use App\Models\PlatformAdministrator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

/** @extends Factory<PlatformAdministrator> */
class PlatformAdministratorFactory extends Factory
{
    protected $model = PlatformAdministrator::class;

    public function definition(): array
    {
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey(16);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'platform_admin',
            'permissions' => PlatformAdministrator::DEFAULT_PERMISSIONS,
            'status' => PlatformAdministrator::ACTIVE,
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(Collection::times(8, fn (): string => RecoveryCode::generate())->all())),
            'two_factor_confirmed_at' => now(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => PlatformAdministrator::DISABLED]);
    }

    public function unenrolled(): static
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }
}

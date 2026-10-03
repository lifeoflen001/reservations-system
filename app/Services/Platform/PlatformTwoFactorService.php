<?php

namespace App\Services\Platform;

use App\Models\PlatformAdministrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final class PlatformTwoFactorService
{
    public const PENDING_ID = 'platform.login.id';
    public const PENDING_REMEMBER = 'platform.login.remember';
    public const RECOVERY_CODES = 'platform.recovery_codes';

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly PlatformAuditService $audit,
    ) {}

    public function beginEnrollment(PlatformAdministrator $administrator): PlatformAdministrator
    {
        if (! $administrator->two_factor_secret) {
            app(EnableTwoFactorAuthentication::class)($administrator);
        }

        return $administrator->fresh();
    }

    public function confirmEnrollment(PlatformAdministrator $administrator, string $code): void
    {
        app(ConfirmTwoFactorAuthentication::class)($administrator, $code);
    }

    public function regenerateRecoveryCodes(PlatformAdministrator $administrator): array
    {
        app(GenerateNewRecoveryCodes::class)($administrator);

        return $administrator->fresh()->recoveryCodes();
    }

    public function verifyCode(PlatformAdministrator $administrator, ?string $code, ?string $recoveryCode): bool
    {
        if ($code !== null && $code !== '' && $administrator->two_factor_secret) {
            return $this->provider->verify(
                Fortify::currentEncrypter()->decrypt($administrator->two_factor_secret),
                $code,
            );
        }

        if ($recoveryCode === null || $recoveryCode === '' || ! $administrator->two_factor_recovery_codes) {
            return false;
        }

        $validCode = collect($administrator->recoveryCodes())->first(
            fn (string $stored): bool => hash_equals($stored, trim($recoveryCode)),
        );

        if (! $validCode) {
            return false;
        }

        $administrator->replaceRecoveryCode($validCode);

        return true;
    }

    public function pendingAdministrator(Request $request): ?PlatformAdministrator
    {
        $id = $request->session()->get(self::PENDING_ID);

        if (! $id) {
            return null;
        }

        $administrator = PlatformAdministrator::query()->whereKey($id)->where('status', PlatformAdministrator::ACTIVE)->first();
        if (! $administrator) {
            $this->clearPending($request);
        }

        return $administrator;
    }

    public function setPending(Request $request, PlatformAdministrator $administrator, bool $remember): void
    {
        $request->session()->put([
            self::PENDING_ID => $administrator->getKey(),
            self::PENDING_REMEMBER => $remember,
        ]);
    }

    public function clearPending(Request $request): void
    {
        $request->session()->forget([self::PENDING_ID, self::PENDING_REMEMBER]);
    }

    public function completePendingLogin(Request $request, PlatformAdministrator $administrator, string $auditAction = 'platform.login.2fa_verified'): void
    {
        Auth::guard('platform')->login($administrator, (bool) $request->session()->pull(self::PENDING_REMEMBER, false));
        $this->clearPending($request);
        $request->session()->regenerate();
        $administrator->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->audit->record($administrator, $auditAction, $administrator, [], null, null, $request);
    }

    public function limiterKey(Request $request, string $suffix = 'challenge'): string
    {
        return 'platform-2fa:'.($request->session()->get(self::PENDING_ID) ?: 'unknown').'|'.$request->ip().'|'.$suffix;
    }

    public function tooManyAttempts(Request $request, string $suffix = 'challenge'): bool
    {
        return RateLimiter::tooManyAttempts($this->limiterKey($request, $suffix), 6);
    }

    public function hit(Request $request, string $suffix = 'challenge'): void
    {
        RateLimiter::hit($this->limiterKey($request, $suffix), 60);
    }

    public function clearLimiter(Request $request, string $suffix = 'challenge'): void
    {
        RateLimiter::clear($this->limiterKey($request, $suffix));
    }
}

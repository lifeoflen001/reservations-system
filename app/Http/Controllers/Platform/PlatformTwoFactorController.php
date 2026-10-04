<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PlatformTwoFactorController extends Controller
{
    public function enroll(Request $request, PlatformTwoFactorService $twoFactor): View|RedirectResponse
    {
        $administrator = $twoFactor->pendingAdministrator($request);
        if (! $administrator) return redirect()->route('platform.login');
        if ($administrator->hasEnabledTwoFactorAuthentication()) return redirect()->route('platform.2fa.challenge');

        return view('platform.auth.two-factor-enroll', ['administrator' => $twoFactor->beginEnrollment($administrator)]);
    }

    public function confirmEnrollment(Request $request, PlatformTwoFactorService $twoFactor): RedirectResponse
    {
        $administrator = $twoFactor->pendingAdministrator($request);
        if (! $administrator) return redirect()->route('platform.login');
        if ($twoFactor->tooManyAttempts($request, 'enroll')) return back()->withErrors(['code' => 'Too many verification attempts. Please try again shortly.']);

        $data = $request->validate(['code' => ['required', 'digits:6']]);
        try {
            $twoFactor->confirmEnrollment($administrator, $data['code']);
        } catch (\Illuminate\Validation\ValidationException) {
            $twoFactor->hit($request, 'enroll');
            throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'The authenticator code was invalid.']);
        }

        $twoFactor->clearLimiter($request, 'enroll');
        $audit = app(PlatformAuditService::class);
        $audit->record($administrator, 'platform.2fa.enrolled', $administrator, [], null, null, $request);
        $twoFactor->completePendingLogin($request, $administrator);
        $request->session()->put(PlatformTwoFactorService::RECOVERY_CODES, $administrator->fresh()->recoveryCodes());

        return redirect()->route('platform.2fa.recovery');
    }

    public function challenge(Request $request, PlatformTwoFactorService $twoFactor): View|RedirectResponse
    {
        $administrator = $twoFactor->pendingAdministrator($request);
        if (! $administrator) return redirect()->route('platform.login');
        if (! $administrator->hasEnabledTwoFactorAuthentication()) return redirect()->route('platform.2fa.enroll');

        return view('platform.auth.two-factor-challenge', ['administrator' => $administrator]);
    }

    public function verifyChallenge(Request $request, PlatformTwoFactorService $twoFactor): RedirectResponse
    {
        $administrator = $twoFactor->pendingAdministrator($request);
        if (! $administrator) return redirect()->route('platform.login');
        if ($twoFactor->tooManyAttempts($request)) return back()->withErrors(['code' => 'Too many verification attempts. Please try again shortly.']);

        $data = $request->validate(['code' => ['nullable', 'digits:6'], 'recovery_code' => ['nullable', 'string', 'max:64']]);
        if (blank($data['code'] ?? null) && blank($data['recovery_code'] ?? null)) {
            return back()->withErrors(['code' => 'Enter an authenticator code or recovery code.']);
        }
        if (! $twoFactor->verifyCode($administrator, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            $twoFactor->hit($request);
            return back()->withErrors(['code' => 'The verification code is invalid or has already been used.'])->withInput();
        }

        $twoFactor->clearLimiter($request);
        app(PlatformAuditService::class)->record($administrator, 'platform.2fa.challenge_verified', $administrator, [], null, null, $request);
        $twoFactor->completePendingLogin($request, $administrator);

        return redirect()->intended(route('platform.dashboard'));
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->pull(PlatformTwoFactorService::RECOVERY_CODES);
        if (! is_array($codes)) return redirect()->route('platform.dashboard');

        return view('platform.auth.recovery-codes', ['codes' => $codes]);
    }

    public function regenerate(Request $request, PlatformTwoFactorService $twoFactor): RedirectResponse
    {
        $administrator = $request->user('platform');
        if ($twoFactor->tooManyAttempts($request, 'recovery')) {
            return back()->withErrors(['code' => 'Too many verification attempts. Please try again shortly.']);
        }

        $data = $request->validate(['current_password' => ['required', 'current_password:platform'], 'code' => ['nullable', 'digits:6'], 'recovery_code' => ['nullable', 'string', 'max:64']]);
        if ((blank($data['code'] ?? null) && blank($data['recovery_code'] ?? null)) || ! $twoFactor->verifyCode($administrator, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            $twoFactor->hit($request, 'recovery');
            throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'Confirm your current authenticator or recovery code.']);
        }

        $twoFactor->clearLimiter($request, 'recovery');
        $codes = $twoFactor->regenerateRecoveryCodes($administrator);
        app(PlatformAuditService::class)->record($administrator, 'platform.2fa.recovery_codes_regenerated', $administrator, [], null, null, $request);

        return back()->with(PlatformTwoFactorService::RECOVERY_CODES, $codes)->with('success', 'Recovery codes regenerated. Store them securely.');
    }

    public function reset(Request $request, PlatformTwoFactorService $twoFactor): RedirectResponse
    {
        $administrator = $request->user('platform');
        if ($twoFactor->tooManyAttempts($request, 'reset')) {
            return back()->withErrors(['code' => 'Too many verification attempts. Please try again shortly.']);
        }

        $data = $request->validate(['current_password' => ['required', 'current_password:platform'], 'code' => ['nullable', 'digits:6'], 'recovery_code' => ['nullable', 'string', 'max:64']]);
        if ((blank($data['code'] ?? null) && blank($data['recovery_code'] ?? null)) || ! $twoFactor->verifyCode($administrator, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            $twoFactor->hit($request, 'reset');
            throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'Confirm your current authenticator or recovery code.']);
        }

        $twoFactor->clearLimiter($request, 'reset');
        app(\Laravel\Fortify\Actions\DisableTwoFactorAuthentication::class)($administrator);
        app(PlatformAuditService::class)->record($administrator, 'platform.2fa.reset', $administrator, [], null, null, $request);
        Auth::guard('platform')->logout();
        $request->session()->forget('platform.support_session_id');
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')->with('success', 'Two-factor authentication was reset. Sign in again to enroll a new authenticator.');
    }
}

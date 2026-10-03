<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdministrator;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlatformAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::guard('platform')->check()) {
            if (Auth::guard('platform')->user()->hasEnabledTwoFactorAuthentication()) {
                return redirect()->route('platform.dashboard');
            }

            Auth::guard('platform')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return view('platform.auth.login');
    }

    public function store(Request $request, PlatformAuditService $audit, PlatformTwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);
        $email = Str::lower(trim($data['email']));
        $key = 'platform-login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 6)) {
            return back()->withErrors(['email' => 'Too many attempts. Please try again shortly.'])->onlyInput('email');
        }

        $administrator = PlatformAdministrator::query()->where('email', $email)->first();
        if (! $administrator || ! $administrator->isActive() || ! Hash::check($data['password'], $administrator->getAuthPassword())) {
            RateLimiter::hit($key, 60);
            $audit->record(null, 'platform.login.failed', null, ['reason' => 'invalid_credentials'], null, null, $request);
            return back()->withErrors(['email' => 'Those Platform Administration credentials were not accepted.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $twoFactor->setPending($request, $administrator, (bool) ($data['remember'] ?? false));

        return $administrator->hasEnabledTwoFactorAuthentication()
            ? redirect()->route('platform.2fa.challenge')
            : redirect()->route('platform.2fa.enroll');
    }

    public function destroy(Request $request, PlatformAuditService $audit): RedirectResponse
    {
        $administrator = Auth::guard('platform')->user();
        if ($administrator) $audit->record($administrator, 'platform.logout', $administrator, [], null, null, $request);
        Auth::guard('platform')->logout();
        $request->session()->forget([
            PlatformTwoFactorService::PENDING_ID,
            PlatformTwoFactorService::PENDING_REMEMBER,
            PlatformTwoFactorService::RECOVERY_CODES,
            'platform.support_session_id',
        ]);
        // Rotate and destroy the previous session ID while preserving the
        // independently authenticated customer guard in this browser.
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginHistoryService;
use App\Services\Tenancy\TenantContext;
use App\Services\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('post-auth') : view('auth.login');
    }

    public function store(LoginRequest $request, LoginHistoryService $loginHistory, TenantContext $tenantContext): RedirectResponse
    {
        $user = $request->authenticate();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->regenerate();
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => $request->boolean('remember')]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $tenantContext->resolveFor($user);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $loginHistory->record($user, $request);

        if ($user->must_change_password) {
            return redirect()->route('password.change')->with('warning', 'Please change your password before continuing.');
        }

        if ($user->email_verification_required && ! $user->hasVerifiedEmail()) return redirect()->route('verification.notice');
        if (app(OnboardingService::class)->stateFor($user)) return redirect()->route('onboarding.start');
        return redirect()->intended(route('dashboard'))->with('success', 'Welcome back.');
    }

    public function postAuth(Request $request, OnboardingService $onboarding, TenantContext $tenantContext): RedirectResponse
    {
        $user = $request->user();
        if ($user->email_verification_required && ! $user->hasVerifiedEmail()) return redirect()->route('verification.notice');
        if ($onboarding->stateFor($user)) return redirect()->route('onboarding.start');
        $tenantContext->resolveFor($user);
        return redirect()->route('dashboard');
    }

    public function destroy(Request $request, LoginHistoryService $loginHistory, TenantContext $tenantContext): RedirectResponse
    {
        $loginHistory->logoutCurrent($request);
        $tenantContext->clear();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

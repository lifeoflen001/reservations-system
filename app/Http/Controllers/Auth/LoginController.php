<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function store(LoginRequest $request, LoginHistoryService $loginHistory): RedirectResponse
    {
        $user = $request->authenticate();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->regenerate();
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => $request->boolean('remember')]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $loginHistory->record($user, $request);

        if ($user->must_change_password) {
            return redirect()->route('password.change')->with('warning', 'Please change your password before continuing.');
        }

        return redirect()->intended(route('dashboard'))->with('success', 'Welcome back.');
    }

    public function destroy(Request $request, LoginHistoryService $loginHistory): RedirectResponse
    {
        $loginHistory->logoutCurrent($request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

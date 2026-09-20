<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\User;
use App\Services\LoginHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class TwoFactorController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->challengedUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', ['user' => $user]);
    }

    public function store(TwoFactorChallengeRequest $request, TwoFactorAuthenticationProvider $provider, LoginHistoryService $loginHistory): RedirectResponse
    {
        $user = $this->challengedUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $valid = false;
        if ($request->filled('code') && $user->two_factor_secret) {
            $valid = $provider->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $request->validated('code'));
        } elseif ($request->filled('recovery_code') && $user->two_factor_recovery_codes) {
            $validCode = collect($user->recoveryCodes())->first(fn (string $code): bool => hash_equals($code, $request->validated('recovery_code')));
            if ($validCode) {
                $user->replaceRecoveryCode($validCode);
                $valid = true;
            }
        }

        if (! $valid) {
            return back()->withErrors(['code' => 'The verification code is invalid or has already been used.'])->withInput();
        }

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $loginHistory->record($user, $request);

        if ($user->must_change_password) {
            return redirect()->route('password.change')->with('warning', 'Please change your password before continuing.');
        }

        return redirect()->intended(route('dashboard'))->with('success', 'Welcome back.');
    }

    private function challengedUser(Request $request): ?User
    {
        $id = $request->session()->get('login.id');

        return $id ? User::find($id) : null;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $administrator = Auth::guard('platform')->user();

        if (! $administrator?->hasEnabledTwoFactorAuthentication()) {
            Auth::guard('platform')->logout();
            $request->session()->forget(['platform.support_session_id', 'platform.login.id', 'platform.login.remember']);

            return redirect()->route('platform.login')->withErrors(['email' => 'Platform two-factor authentication must be enrolled before using the control plane.']);
        }

        return $next($request);
    }
}

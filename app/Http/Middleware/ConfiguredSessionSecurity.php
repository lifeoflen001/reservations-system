<?php

namespace App\Http\Middleware;

use App\Services\LoginHistoryService;
use App\Services\SystemSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ConfiguredSessionSecurity
{
    public function __construct(private readonly LoginHistoryService $loginHistory) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            if ($this->loginHistory->isRevoked($request)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('warning', 'This device has been signed out. Please sign in again.');
            }

            $timeout = max(5, app(SystemSettingsService::class)->integer('session_timeout', 120));
            $lastActivity = (int) $request->session()->get('hotel.last_activity', 0);
            if ($lastActivity && now()->timestamp - $lastActivity > $timeout * 60) {
                $this->loginHistory->logoutCurrent($request);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('warning', 'Your session expired. Please sign in again.');
            }
            $request->session()->put('hotel.last_activity', now()->timestamp);
            $this->loginHistory->touch($request);
        }

        return $next($request);
    }
}

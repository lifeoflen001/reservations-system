<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformTwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformTwoFactorPending
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(PlatformTwoFactorService::class)->pendingAdministrator($request)) {
            return redirect()->route('platform.login');
        }

        return $next($request);
    }
}

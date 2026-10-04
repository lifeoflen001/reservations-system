<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivePlatformAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $administrator = Auth::guard('platform')->user();
        abort_unless($administrator?->isActive(), 403, 'This Platform Administrator account is disabled.');

        return $next($request);
    }
}

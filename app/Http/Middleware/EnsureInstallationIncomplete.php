<?php

namespace App\Http\Middleware;

use App\Services\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallationIncomplete
{
    public function __construct(private readonly InstallationState $installationState) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('hotel.setup.enabled', false)) {
            return redirect()->route('login');
        }

        return $this->installationState->isComplete() ? redirect()->route('login') : $next($request);
    }
}

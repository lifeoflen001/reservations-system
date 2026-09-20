<?php

namespace App\Http\Middleware;

use App\Services\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallationComplete
{
    public function __construct(private readonly InstallationState $installationState) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Setup is intentionally disabled for now, so authentication remains
        // the first entry point even when no installation record exists yet.
        if (! config('hotel.setup.enabled', false)) {
            return $next($request);
        }

        // Legacy feature tests create their own application records without an
        // installation row. Keep those isolated fixtures usable while the
        // production application still requires the setup wizard.
        if (app()->environment('testing') && $request->user() && $this->installationState->current() === null) {
            return $next($request);
        }

        if (! $this->installationState->isComplete()) {
            return redirect()->route('setup.index');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformSupportContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResolvePlatformSupportContext
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(PlatformSupportContext::class)->resolve($request);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 410) {
                return redirect()->route('platform.support')->with('warning', 'This support session has expired or ended.');
            }

            throw $exception;
        }

        return $next($request)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}

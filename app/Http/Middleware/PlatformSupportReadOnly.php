<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformSupportContext;
use App\Services\Platform\SupportAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformSupportReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $session = app(PlatformSupportContext::class)->current();
            app(PlatformAuditService::class)->record($request->user('platform'), 'support.mutation_blocked', $session, ['method' => $request->method(), 'path' => $request->path()], $session->organization_id, $session->property_id, $request);

            return response()->view('errors.403', ['status' => 403], 403);
        }

        return $next($request);
    }
}

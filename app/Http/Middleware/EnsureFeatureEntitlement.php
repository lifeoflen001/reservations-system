<?php

namespace App\Http\Middleware;

use App\Services\EntitlementService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFeatureEntitlement
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $organization = app(TenantContext::class)->requireOrganization();
        if (app(EntitlementService::class)->hasFeature($organization, $feature)) return $next($request);

        abort(403, app(EntitlementService::class)->explainFeatureDenial($organization, $feature));
    }
}

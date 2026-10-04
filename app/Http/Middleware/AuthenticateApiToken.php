<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Organization;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        // The bearer token is the credential that establishes tenant context,
        // so it must be looked up before the request context exists. Tenant
        // ownership is enforced immediately after this lookup by activate().
        $token = $plain ? ApiToken::query()
            ->withoutGlobalScope('tenant-ownership')
            ->with(['user' => fn ($query) => $query->withoutGlobalScope('tenant-ownership')])
            ->where('token_hash', hash('sha256', $plain))
            ->first() : null;
        if (! $token || ! $token->active() || ! $token->user?->is_active) {
            return new JsonResponse(['message' => 'A valid API token is required.'], 401);
        }
        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $token->user);
        if ($token->organization_id === null || $token->property_id === null) {
            if (Organization::query()->exists()) {
                return new JsonResponse(['message' => 'This API token is not assigned to an active tenant context.'], 403);
            }
        } else {
            app(TenantContext::class)->activate((int) $token->organization_id, (int) $token->property_id);
        }

        return $next($request);
    }
}

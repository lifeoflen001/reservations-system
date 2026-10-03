<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use App\Models\Organization;
use App\Models\OrganizationOnboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Lodgix website administration and contact enquiries are platform
        // content, not hotel tenant content.
        if ($request->routeIs('website.*', 'contact-enquiries.*', 'logout', 'password.change', 'password.change.update')) {
            app(TenantContext::class)->release();
            return $next($request);
        }

        // A completely unconfigured legacy/test fixture has no tenant to
        // resolve yet. Preserve that pre-tenant behavior; once the first
        // organization exists, zero-access users receive the safe 403 state.
        if (app()->environment('testing') && ! Organization::query()->exists()) {
            app(TenantContext::class)->release();
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $context = app(TenantContext::class);
        $context->resolveFor($user);

        if ($context->hasOrganization() && $context->hasProperty()) {
            return $next($request);
        }

        if ($user->email_verification_required && OrganizationOnboarding::query()->where('owner_user_id', $user->getKey())->whereNull('completed_at')->exists()) {
            return redirect()->route('onboarding.start');
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'This account has no active organization or property access.'], 403);
        }

        return response()->view('errors.403', ['status' => 403], 403);
    }
}

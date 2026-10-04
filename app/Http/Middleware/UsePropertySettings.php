<?php

namespace App\Http\Middleware;

use App\Services\PropertySettingsService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UsePropertySettings
{
    public function handle(Request $request, Closure $next): Response
    {
        // The public landing page is intentionally static/config-driven. Do
        // not read property records just to render marketing content.
        if ($request->routeIs('public.*', 'sitemap', 'robots', 'website.*', 'contact-enquiries.*', 'platform.*')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        app(TenantContext::class)->resolveFor($user);
        $settings = app(PropertySettingsService::class);
        $timezone = $settings->timezone();
        $locale = $settings->locale();
        config(['app.timezone' => $timezone, 'app.locale' => $locale]);
        date_default_timezone_set($timezone);
        app()->setLocale($locale);

        return $next($request);
    }
}

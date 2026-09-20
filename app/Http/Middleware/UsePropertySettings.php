<?php

namespace App\Http\Middleware;

use App\Services\PropertySettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UsePropertySettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(PropertySettingsService::class);
        $timezone = $settings->timezone();
        $locale = $settings->locale();
        config(['app.timezone' => $timezone, 'app.locale' => $locale]);
        date_default_timezone_set($timezone);
        app()->setLocale($locale);

        return $next($request);
    }
}

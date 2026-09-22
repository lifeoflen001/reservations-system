<?php

use App\Http\Middleware\ConfiguredSessionSecurity;
use App\Http\Middleware\EnsureInstallationComplete;
use App\Http\Middleware\EnsureInstallationIncomplete;
use App\Http\Middleware\UsePropertySettings;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway terminates TLS before forwarding requests to the PHP
        // process. Trust its forwarded headers so Laravel preserves the
        // original HTTPS scheme for redirects, cookies and CSRF sessions.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->web(append: [UsePropertySettings::class]);
        $middleware->alias([
            'installation.complete' => EnsureInstallationComplete::class,
            'installation.incomplete' => EnsureInstallationIncomplete::class,
            'configured.session' => ConfiguredSessionSecurity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API/database failures must remain machine-readable instead of being
        // rendered as an HTML page or mistaken for an empty data response.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson());
    })->create();

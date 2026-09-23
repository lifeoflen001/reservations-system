<?php

use App\Http\Middleware\ConfiguredSessionSecurity;
use App\Http\Middleware\EnsureInstallationComplete;
use App\Http\Middleware\EnsureInstallationIncomplete;
use App\Http\Middleware\UsePropertySettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('announcements:sync')->everyMinute();
    })
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

        $exceptions->renderable(function (Throwable $exception, Request $request) {
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : null;
            $isForbidden = $exception instanceof AuthorizationException || $status === 403;
            if ($request->expectsJson() || ! $isForbidden) {
                return null;
            }

            return response()->view('errors.403', status: 403);
        });
    })->create();

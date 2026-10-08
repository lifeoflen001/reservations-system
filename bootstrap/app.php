<?php

use App\Http\Middleware\ConfiguredSessionSecurity;
use App\Http\Middleware\EnsureInstallationComplete;
use App\Http\Middleware\EnsureInstallationIncomplete;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UsePropertySettings;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\EnsurePlatformAuthenticated;
use App\Http\Middleware\EnsureActivePlatformAdministrator;
use App\Http\Middleware\EnsurePlatformPermission;
use App\Http\Middleware\EnsurePlatformTwoFactor;
use App\Http\Middleware\EnsurePlatformTwoFactorPending;
use App\Http\Middleware\ResolvePlatformSupportContext;
use App\Http\Middleware\PlatformSupportReadOnly;
use App\Http\Middleware\EnsureFeatureEntitlement;
use App\Http\Middleware\EnsureCustomerEmailVerified;
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
        $middleware->append(SecurityHeaders::class);
        // Railway terminates TLS before forwarding requests to the PHP
        // process. Trust its forwarded headers so Laravel preserves the
        // original HTTPS scheme for redirects, cookies and CSRF sessions.
        // Only trust forwarded headers from explicitly configured reverse
        // proxies. Trusting every client allows a direct request to spoof the
        // original scheme/host and influence secure-cookie and URL behavior.
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
        // The test suite intentionally simulates a proxy from an in-memory
        // request. This exception never applies to production deployments.
        if ($trustedProxies === '' && env('APP_ENV') === 'testing') {
            $trustedProxies = '*';
        }
        $trustedProxyValue = $trustedProxies === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $trustedProxies))));
        $middleware->trustProxies(at: $trustedProxyValue ?: null);
        // Provider callbacks authenticate with their signed webhook headers,
        // not a browser session token. Keep them outside Laravel's CSRF check
        // so real gateway deliveries reach signature verification.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->web(append: [UsePropertySettings::class]);
        $middleware->alias([
            'installation.complete' => EnsureInstallationComplete::class,
            'installation.incomplete' => EnsureInstallationIncomplete::class,
            'configured.session' => ConfiguredSessionSecurity::class,
            'tenant.context' => ResolveTenantContext::class,
            'platform.auth' => EnsurePlatformAuthenticated::class,
            'platform.active' => EnsureActivePlatformAdministrator::class,
            'platform.permission' => EnsurePlatformPermission::class,
            'platform.2fa' => EnsurePlatformTwoFactor::class,
            'platform.2fa.pending' => EnsurePlatformTwoFactorPending::class,
            'platform.support.context' => ResolvePlatformSupportContext::class,
            'platform.support.readonly' => PlatformSupportReadOnly::class,
            'feature' => EnsureFeatureEntitlement::class,
            'customer.verified' => EnsureCustomerEmailVerified::class,
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

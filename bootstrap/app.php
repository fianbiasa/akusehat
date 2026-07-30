<?php

use App\Http\Middleware\EnsureNotInMaintenanceMode;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureWithinProgramLimit;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    // Listener registration is explicit in AppServiceProvider (ordering
    // matters - e.g. health-profile mapping must run before program
    // generation is dispatched). Auto-discovery would double-register
    // every listener on top of that.
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EnsureNotInMaintenanceMode::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'permission' => EnsurePermission::class,
            'onboarding.completed' => EnsureOnboardingCompleted::class,
            'plan.program_limit' => EnsureWithinProgramLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Inactive until SENTRY_LARAVEL_DSN is set (config/sentry.php
        // defaults it to null) - Sentry's own SDK no-ops entirely without
        // a DSN, so this is safe to leave wired in permanently rather
        // than conditionally registering it.
        Integration::handles($exceptions);
    })->create();

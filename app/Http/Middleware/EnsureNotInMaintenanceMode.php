<?php

namespace App\Http\Middleware;

use App\Services\AppSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DB-backed maintenance mode (Admin-toggleable from `/admin/settings`,
 * `app_settings` key `maintenance_mode`) - distinct from Laravel's
 * built-in file-based `php artisan down`, which needs shell access an
 * Admin operator doesn't have. Admins always pass through (so they can
 * turn it back off), and the login route always stays reachable so an
 * Admin isn't locked out by their own toggle before authenticating.
 */
class EnsureNotInMaintenanceMode
{
    public function __construct(private AppSettingsService $appSettings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->appSettings->isMaintenanceMode()) {
            return $next($request);
        }

        if ($request->routeIs('login', 'logout')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        abort(503, $this->appSettings->maintenanceMessage() ?? 'Platform sedang dalam perawatan. Silakan coba lagi beberapa saat lagi.');
    }
}

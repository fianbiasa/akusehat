<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    /**
     * Members must finish the onboarding wizard before reaching the rest of
     * the app (FR-ONB-04). Coach/Admin accounts are never onboarded, so this
     * only gates the member role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->hasRole('member') && ! $user->onboarding_completed_at) {
            return redirect()->route('onboarding.index');
        }

        return $next($request);
    }
}

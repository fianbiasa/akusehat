<?php

namespace App\Http\Middleware;

use App\Services\Subscription\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan-based gating (FR-SUB, `plans.max_programs`) for starting a new
 * program (`POST /user-programs`) - the one write path where the acting
 * user's own limit is all that matters, so this fits real route
 * middleware cleanly (unlike `has_coach_access`, which gates a specific
 * *member* being assigned a coach and is checked inline in
 * Admin\CoachAssignmentController instead, since that needs the route's
 * target user, not the request's).
 */
class EnsureWithinProgramLimit
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->subscriptions->withinProgramLimit($request->user()), 403, 'Batas jumlah program pada paket langgananmu sudah tercapai.');

        return $next($request);
    }
}

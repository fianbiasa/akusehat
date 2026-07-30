<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $subscription = $this->subscriptions->currentSubscription($user)->load('plan');

        return Inertia::render('settings/subscription', [
            'subscription' => $subscription,
            'usage' => [
                'active_programs' => $user->activePrograms()->count(),
                'max_programs' => $subscription->plan->max_programs,
            ],
            'plans' => Plan::where('is_active', true)->orderBy('price')->get(),
            'payments' => Payment::whereHas('subscription', fn ($q) => $q->where('user_id', $user->id))
                ->with('subscription.plan:id,name')
                ->latest()
                ->get(),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        $this->subscriptions->subscribe($request->user(), $plan);

        return back();
    }

    public function cancel(Request $request): RedirectResponse
    {
        $subscription = $this->subscriptions->currentSubscription($request->user());

        abort_if((float) $subscription->plan->price === 0.0, 422, 'Tidak ada langganan berbayar untuk dibatalkan.');
        abort_if($subscription->cancelled_at !== null, 422, 'Langganan sudah dijadwalkan untuk berakhir.');

        $this->subscriptions->cancel($subscription);

        return back();
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = Payment::whereHas('subscription', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('subscription.plan:id,name')
            ->latest()
            ->get();

        return response()->json($payments);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $subscriptions = Subscription::with(['user:id,name,email', 'plan:id,name'])
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('plan_id') ?: null, fn ($q, $planId) => $q->where('plan_id', $planId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/subscriptions/index', [
            'subscriptions' => $subscriptions,
            'plans' => Plan::orderBy('price')->get(['id', 'name']),
            'filters' => $request->only(['status', 'plan_id']),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoachProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('coach/profile', [
            'profile' => $request->user()->coachProfile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bio' => ['nullable', 'string', 'max:2000'],
            'specialization' => ['nullable', 'string', 'max:150'],
            'certification' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->coachProfile()->update($validated);

        return back();
    }
}

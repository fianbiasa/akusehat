<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\UserMedication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserMedicationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'dosage' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'started_at' => ['nullable', 'date'],
        ]);

        $request->user()->medications()->create($validated);

        return back();
    }

    public function update(Request $request, UserMedication $medication): RedirectResponse
    {
        abort_unless($medication->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'dosage' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'started_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $medication->update($validated);

        return back();
    }

    public function destroy(Request $request, UserMedication $medication): RedirectResponse
    {
        abort_unless($medication->user_id === $request->user()->id, 403);

        $medication->delete();

        return back();
    }
}

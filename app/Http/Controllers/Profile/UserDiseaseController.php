<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\UserDisease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserDiseaseController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kb_disease_id' => ['required', 'exists:kb_diseases,id'],
            'diagnosed_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['active', 'managed', 'resolved'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $request->user()->diseases()->create($validated);

        return back();
    }

    public function destroy(Request $request, UserDisease $disease): RedirectResponse
    {
        abort_unless($disease->user_id === $request->user()->id, 403);

        $disease->delete();

        return back();
    }
}

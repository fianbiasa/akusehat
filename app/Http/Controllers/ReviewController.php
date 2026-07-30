<?php

namespace App\Http\Controllers;

use App\Models\CoachProfile;
use App\Models\Review;
use App\Models\UserProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $user->hasRole('admin')
            ? Review::query()
            : $user->reviewsReceived();

        return response()->json($query->with(['coach:id,name', 'member:id,name'])->latest()->get());
    }

    /**
     * One review per (coach, member) pair - a member updates their
     * existing review rather than creating duplicates (Database
     * Dictionary Module 08).
     */
    public function store(Request $request, UserProgram $userProgram): RedirectResponse
    {
        abort_unless($userProgram->user_id === $request->user()->id, 403);
        abort_if($userProgram->coach_id === null, 422, 'This program has no assigned coach to review.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        Review::updateOrCreate(
            ['coach_id' => $userProgram->coach_id, 'member_id' => $request->user()->id],
            $validated,
        );

        $average = Review::where('coach_id', $userProgram->coach_id)->avg('rating');
        CoachProfile::where('user_id', $userProgram->coach_id)->update(['rating_avg' => round($average, 2)]);

        return back();
    }
}

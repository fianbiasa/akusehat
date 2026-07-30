<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachMember;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoachAssignmentController extends Controller
{
    public function __construct(private CoachAssignmentService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(
            CoachMember::with(['coach:id,name', 'member:id,name'])->where('status', 'active')->latest()->get()
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $coachRoleId = Role::where('name', 'coach')->value('id');

        $validated = $request->validate([
            'coach_id' => ['required', Rule::exists('users', 'id')->where('role_id', $coachRoleId)],
            'member_id' => ['required', 'exists:users,id'],
        ]);

        $coach = User::findOrFail($validated['coach_id']);
        $member = User::findOrFail($validated['member_id']);

        $this->service->assign($coach, $member);

        return back();
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $this->service->unassign($member);

        return back();
    }
}

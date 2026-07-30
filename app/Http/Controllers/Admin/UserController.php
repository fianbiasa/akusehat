<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('role')
            ->when($request->string('q')->trim()->toString(), fn ($query, $q) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
            ))
            ->when($request->string('role')->toString(), fn ($query, $role) => $query->whereRelation('role', 'name', $role))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name', 'label']),
            'filters' => $request->only(['q', 'role', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'status' => ['required', Rule::in(['active', 'suspended', 'pending'])],
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        // email_verified_at is system-managed, not user-fillable (see
        // App\Models\User) - forceFill bypasses that guard the same way
        // OnboardingController does for onboarding_completed_at.
        $user->forceFill(['email_verified_at' => now()])->save();

        // Coach accounts are Admin-created (FR-COACH); the coach fills in
        // their own bio/specialization afterward, so the row always
        // exists rather than every coach-facing query null-coalescing.
        if (Role::find($validated['role_id'])?->name === 'coach') {
            $user->coachProfile()->create([]);
        }

        $this->activityLogger->log('user.created', $user, ['email' => $user->email, 'role_id' => $user->role_id]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['required', 'exists:roles,id'],
            'status' => ['required', Rule::in(['active', 'suspended', 'pending'])],
        ]);

        $user->update($validated);

        $this->activityLogger->log('user.updated', $user, $validated);

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is(request()->user()), 422, 'You cannot delete your own account here.');

        $this->activityLogger->log('user.deleted', $user, ['email' => $user->email]);

        $user->delete();

        return back();
    }
}

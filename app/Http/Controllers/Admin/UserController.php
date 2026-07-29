<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
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

        User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

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

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is(request()->user()), 422, 'You cannot delete your own account here.');

        $user->delete();

        return back();
    }
}

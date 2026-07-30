<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Admin\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(): Response
    {
        return Inertia::render('admin/roles/index', [
            'roles' => Role::with('permissions:id,name,module')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('module')->orderBy('name')->get(['id', 'name', 'module']),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permission_ids'] ?? []);

        $this->activityLogger->log('role.permissions_updated', $role, ['permission_ids' => $validated['permission_ids'] ?? []]);

        return back();
    }
}

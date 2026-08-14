<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesController extends Controller
{
    /**
     * Display roles with their permissions.
     */
    public function index(): Response
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
            ]);

        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'group' => explode('.', $permission->name)[0],
            ]);

        return Inertia::render('admin/roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update the permissions of a role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        // Le rôle superadmin est protégé : ses permissions ne peuvent pas
        // être modifiées, il dispose toujours de tous les droits.
        if ($role->name === 'superadmin') {
            abort(403);
        }

        $role->syncPermissions($request->input('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permissions mises à jour avec succès.']);

        return back();
    }
}

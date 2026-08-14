<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithPermissions
{
    /**
     * Forget cached permissions so freshly seeded permissions are visible.
     */
    protected function setUpPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create a user granted the given permissions.
     *
     * @param  array<int, string>  $permissions
     */
    protected function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * Create a superadmin user. Thanks to the Gate::before registered in
     * AppServiceProvider, it bypasses every permission check.
     */
    protected function superadminUser(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate('superadmin');
        $user->assignRole('superadmin');

        return $user;
    }
}

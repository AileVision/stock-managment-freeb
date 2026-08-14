<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    public function test_guests_are_redirected_to_login_for_roles(): void
    {
        $this->get(route('roles.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_roles(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_admin_with_users_manage_cannot_view_roles(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_roles_page_lists_roles_with_their_permissions(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        Permission::findOrCreate('products.view');
        Permission::findOrCreate('products.create');
        $role = Role::findOrCreate('gestionnaire');
        $role->syncPermissions(['products.view', 'products.create']);

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/roles')
                ->has('roles', 1)
                ->where('roles.0.name', 'gestionnaire')
                ->where('roles.0.permissions', ['products.view', 'products.create'])
                ->has('permissions', 3)
                ->where('permissions.0.group', 'products')
                ->where('permissions.2.group', 'roles'));
    }

    public function test_admin_can_update_a_role_permissions(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        Permission::findOrCreate('products.view');
        Permission::findOrCreate('products.delete');
        $role = Role::findOrCreate('gestionnaire');

        $this->actingAs($admin)
            ->patch(route('roles.update', $role), [
                'permissions' => ['products.view'],
            ])
            ->assertRedirect();

        $role->refresh();

        $this->assertTrue($role->hasPermissionTo('products.view'));
        $this->assertFalse($role->hasPermissionTo('products.delete'));
    }

    public function test_admin_can_remove_all_permissions_from_a_role(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        Permission::findOrCreate('products.view');
        $role = Role::findOrCreate('gestionnaire');
        $role->syncPermissions(['products.view']);

        $this->actingAs($admin)
            ->patch(route('roles.update', $role), ['permissions' => []])
            ->assertRedirect();

        $this->assertSame(0, $role->permissions()->count());
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        $role = Role::findOrCreate('gestionnaire');

        $this->actingAs($admin)
            ->patch(route('roles.update', $role), [
                'permissions' => ['inexistante.xyz'],
            ])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_superadmin_role_permissions_cannot_be_modified(): void
    {
        $superadmin = $this->superadminUser();
        $role = Role::findOrCreate('superadmin');
        Permission::findOrCreate('products.view');

        $this->actingAs($superadmin)
            ->patch(route('roles.update', $role), [
                'permissions' => ['products.view'],
            ])
            ->assertForbidden();
    }

    public function test_superadmin_can_manage_other_roles(): void
    {
        $superadmin = $this->superadminUser();
        $role = Role::findOrCreate('gestionnaire');
        Permission::findOrCreate('products.view');

        $this->actingAs($superadmin)
            ->patch(route('roles.update', $role), [
                'permissions' => ['products.view'],
            ])
            ->assertRedirect();

        $this->assertTrue($role->hasPermissionTo('products.view'));
    }
}

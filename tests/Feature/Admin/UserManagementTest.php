<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    public function test_guests_are_redirected_to_login_for_users(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_users(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user_without_assigning_a_role(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        Role::findOrCreate('gestionnaire');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Awa Diallo',
                'email' => 'awa@example.com',
                'password' => 'motdepasse-secret',
            ])
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'awa@example.com')->firstOrFail();
        $this->assertSame('Awa Diallo', $user->name);
        $this->assertTrue(Hash::check('motdepasse-secret', $user->password));
        $this->assertTrue($user->hasRole('gestionnaire'));
    }

    public function test_admin_cannot_assign_a_role_even_when_submitted(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        Role::findOrCreate('gestionnaire');
        Role::findOrCreate('superadmin');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Awa Diallo',
                'email' => 'awa@example.com',
                'password' => 'motdepasse-secret',
                'role' => 'superadmin',
            ])
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'awa@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('gestionnaire'));
        $this->assertFalse($user->hasRole('superadmin'));
    }

    public function test_superadmin_can_create_a_user_with_a_role(): void
    {
        $superadmin = $this->superadminUser();
        Role::findOrCreate('admin');

        $this->actingAs($superadmin)
            ->post(route('users.store'), [
                'name' => 'Awa Diallo',
                'email' => 'awa@example.com',
                'password' => 'motdepasse-secret',
                'role' => 'admin',
            ])
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'awa@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_user_email_must_be_unique(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        User::factory()->create(['email' => 'doublon@example.com']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Doublon',
                'email' => 'doublon@example.com',
                'password' => 'motdepasse-secret',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_a_user_without_changing_the_role(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        Role::findOrCreate('gestionnaire');
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('gestionnaire');

        $this->actingAs($admin)
            ->patch(route('users.update', $user), [
                'name' => 'Nouveau nom',
                'email' => $user->email,
                'password' => 'nouveau-motdepasse',
                'role' => 'admin',
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertSame('Nouveau nom', $user->name);
        $this->assertTrue(Hash::check('nouveau-motdepasse', $user->password));
        $this->assertTrue($user->hasRole('gestionnaire'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_superadmin_can_update_a_user_role(): void
    {
        $superadmin = $this->superadminUser();
        Role::findOrCreate('gestionnaire');
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('gestionnaire');

        $this->actingAs($superadmin)
            ->patch(route('users.update', $user), [
                'name' => 'Nouveau nom',
                'email' => $user->email,
                'role' => 'admin',
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertSame('Nouveau nom', $user->name);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_nobody_can_delete_a_superadmin_account(): void
    {
        $superadmin = $this->superadminUser();
        $admin = $this->userWithPermissions(['users.manage']);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $superadmin))
            ->assertForbidden();

        // Même un autre superadmin ne peut pas supprimer un compte superadmin.
        $otherSuperadmin = $this->superadminUser();

        $this->actingAs($otherSuperadmin)
            ->delete(route('users.destroy', $superadmin))
            ->assertForbidden();

        // Un superadmin ne peut pas non plus supprimer son propre compte.
        $this->actingAs($superadmin)
            ->delete(route('users.destroy', $superadmin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
    }

    public function test_admin_cannot_modify_a_superadmin_account(): void
    {
        $superadmin = $this->superadminUser();
        $admin = $this->userWithPermissions(['users.manage']);

        $this->actingAs($admin)
            ->patch(route('users.update', $superadmin), [
                'name' => 'Modifié',
                'email' => $superadmin->email,
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $superadmin->id,
            'name' => $superadmin->name,
            'email' => $superadmin->email,
        ]);
        $this->assertTrue($superadmin->fresh()->hasRole('superadmin'));
    }

    public function test_superadmin_cannot_be_modified_by_another_superadmin(): void
    {
        $superadmin = $this->superadminUser();
        $otherSuperadmin = $this->superadminUser();
        Role::findOrCreate('admin');

        $this->actingAs($otherSuperadmin)
            ->patch(route('users.update', $superadmin), [
                'name' => 'Modifié',
                'email' => $superadmin->email,
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->assertTrue($superadmin->fresh()->hasRole('superadmin'));
    }

    public function test_superadmin_can_update_their_own_profile(): void
    {
        $superadmin = $this->superadminUser();
        Role::findOrCreate('gestionnaire');

        $this->actingAs($superadmin)
            ->patch(route('users.update', $superadmin), [
                'name' => 'Super admin v2',
                'email' => 'super@example.com',
                'password' => 'nouveau-motdepasse',
                // Tentative de rétrogradation : elle doit être ignorée.
                'role' => 'gestionnaire',
            ])
            ->assertRedirect(route('users.index'));

        $superadmin->refresh();

        $this->assertSame('Super admin v2', $superadmin->name);
        $this->assertSame('super@example.com', $superadmin->email);
        $this->assertTrue(Hash::check('nouveau-motdepasse', $superadmin->password));
        $this->assertTrue($superadmin->hasRole('superadmin'));
    }

    public function test_superadmin_has_full_access_to_the_system(): void
    {
        // Le superadmin n'a aucune permission directe : le Gate::before lui
        // donne accès à tout le système.
        $superadmin = $this->superadminUser();

        $this->actingAs($superadmin)->get(route('dashboard'))->assertOk();
        $this->actingAs($superadmin)->get(route('roles.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('users.index'))->assertOk();
    }

    public function test_superadmin_sees_all_permissions_in_shared_props(): void
    {
        $superadmin = $this->superadminUser();
        Permission::findOrCreate('dashboard.view');
        Permission::findOrCreate('products.delete');

        $this->actingAs($superadmin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', ['dashboard.view', 'products.delete']));
    }
}

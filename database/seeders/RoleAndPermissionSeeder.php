<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Invalide le cache de permissions pour éviter des états obsolètes.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',
            'movements.view',
            'movements.create',
            'reports.view',
            'reports.export',
            'replenishment.view',
            'replenishment.create',
            'users.manage',
            'roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Le registre cache la liste des permissions : il faut la recharger
        // depuis la base avant de résoudre les permissions par leur nom.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Le superadmin dispose de tous les droits (le Gate::before dans
        // AppServiceProvider lui donne aussi un accès total au système).
        $superadmin = Role::findOrCreate('superadmin');
        $superadmin->syncPermissions($permissions);

        // L'admin gère le stock (avec suppressions) et les comptes utilisateurs,
        // mais l'attribution des rôles et la gestion des permissions lui
        // sont retirées : elles sont réservées au superadmin.
        $admin = Role::findOrCreate('admin');
        $admin->syncPermissions(array_values(array_diff($permissions, ['roles.manage'])));

        // Le gestionnaire gère le catalogue et les mouvements, sans suppressions.
        $gestionnaire = Role::findOrCreate('gestionnaire');
        $gestionnaire->syncPermissions([
            'dashboard.view',
            'products.view',
            'products.create',
            'products.update',
            'categories.view',
            'categories.create',
            'categories.update',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'movements.view',
            'movements.create',
            'reports.view',
            'reports.export',
            'replenishment.view',
            'replenishment.create',
        ]);

        // Le compte admin@example.com est le superadmin : accès total et
        // intouchable (aucun utilisateur ne peut le supprimer ou le modifier).
        $superadminUser = User::factory()->create([
            'name' => 'Super administrateur',
            'email' => 'admin@example.com',
        ]);
        $superadminUser->assignRole('superadmin');

        $gestionnaireUser = User::factory()->create([
            'name' => 'Gestionnaire',
            'email' => 'gestionnaire@example.com',
        ]);
        $gestionnaireUser->assignRole('gestionnaire');

        // Invalide une dernière fois le cache pour les contrôles d'accès à venir.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

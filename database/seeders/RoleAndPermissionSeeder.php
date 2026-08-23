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
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'movements.view',
            'movements.create',
            'warehouses.view',
            'warehouses.manage',
            'transfers.view',
            'transfers.create',
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.receive',
            'purchase_orders.delete',
            'sales.view',
            'sales.create',
            'sales.delete',
            'receivables.view',
            'receivables.manage',
            'returns.view',
            'returns.create',
            'supplier_payables.view',
            'supplier_payables.manage',
            'expenses.view',
            'expenses.create',
            'expenses.delete',
            'closings.view',
            'closings.close',
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.delete',
            'invoices.view',
            'invoices.create',
            'sessions.view',
            'sessions.open',
            'sessions.close',
            'reports.view',
            'reports.export',
            'taxes.view',
            'taxes.manage',
            'replenishment.view',
            'replenishment.create',
            'users.manage',
            'roles.manage',
            'backups.manage',
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
            'customers.view',
            'customers.create',
            'customers.update',
            'movements.view',
            'movements.create',
            'warehouses.view',
            'transfers.view',
            'transfers.create',
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.receive',
            'sales.view',
            'sales.create',
            'receivables.view',
            'receivables.manage',
            'returns.view',
            'returns.create',
            'supplier_payables.view',
            'supplier_payables.manage',
            'expenses.view',
            'expenses.create',
            'expenses.delete',
            'closings.view',
            'closings.close',
            'quotes.view',
            'quotes.create',
            'invoices.view',
            'invoices.create',
            'sessions.view',
            'sessions.open',
            'sessions.close',
            'reports.view',
            'reports.export',
            'taxes.view',
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

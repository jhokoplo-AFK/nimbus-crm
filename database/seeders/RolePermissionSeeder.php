<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'leads.view',
        'leads.create',
        'leads.update',
        'leads.delete',
        'leads.convert',
        'accounts.view',
        'accounts.manage',
        'contacts.view',
        'contacts.manage',
        'opportunities.view',
        'opportunities.manage',
        'quotations.view',
        'quotations.create',
        'quotations.update',
        'quotations.approve',
        'contracts.view',
        'orders.view',
        'products.view',
        'products.manage',
        'users.manage',
        'approvals.manage',
    ];

    /**
     * @var array<int, string>
     */
    private const SALES_PERMISSIONS = [
        'leads.view', 'leads.create', 'leads.update', 'leads.convert',
        'accounts.view',
        'contacts.view',
        'opportunities.view', 'opportunities.manage',
        'quotations.view', 'quotations.create', 'quotations.update',
        'contracts.view', 'orders.view', 'products.view',
    ];

    /**
     * @var array<int, string>
     */
    private const MANAGER_PERMISSIONS = [
        'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.convert',
        'accounts.view', 'accounts.manage',
        'contacts.view', 'contacts.manage',
        'opportunities.view', 'opportunities.manage',
        'quotations.view', 'quotations.create', 'quotations.update', 'quotations.approve',
        'contracts.view', 'orders.view', 'products.view', 'products.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('Admin', 'web')->syncPermissions(self::PERMISSIONS);

        // Commercial ladder, highest authority last. The price approval matrix
        // maps these roles to discount and value ceilings.
        Role::findOrCreate('Sales', 'web')->syncPermissions(self::SALES_PERMISSIONS);
        Role::findOrCreate('Manager', 'web')->syncPermissions(self::MANAGER_PERMISSIONS);
        Role::findOrCreate('Regional Manager', 'web')->syncPermissions(self::MANAGER_PERMISSIONS);

        Role::findOrCreate('General Manager', 'web')->syncPermissions([
            ...self::MANAGER_PERMISSIONS,
            'approvals.manage',
        ]);

        Role::findOrCreate('Director', 'web')->syncPermissions(self::PERMISSIONS);

        // Legacy role names stay valid so existing assignments, docs and API
        // consumers keep working; the approval matrix maps them to the same
        // layers as Sales and Manager.
        Role::findOrCreate('Sales Manager', 'web')->syncPermissions(self::MANAGER_PERMISSIONS);
        Role::findOrCreate('Sales Rep', 'web')->syncPermissions(self::SALES_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

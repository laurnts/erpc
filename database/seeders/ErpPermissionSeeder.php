<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class ErpPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define ERP permissions by module
        $permissions = [
            // Company permissions (consolidated from buyers/suppliers)
            'view companies',
            'create companies',
            'update companies',
            'delete companies',

            // Legacy buyer/supplier permissions for backward compatibility
            'view buyers',
            'create buyers',
            'update buyers',
            'delete buyers',
            'view suppliers',
            'create suppliers',
            'update suppliers',
            'delete suppliers',

            // Article permissions
            'view articles',
            'create articles',
            'update articles',
            'delete articles',

            // Request permissions
            'view requests',
            'create requests',
            'update requests',
            'delete requests',

            // Supplier Quote permissions
            'view supplier quotes',
            'create supplier quotes',
            'update supplier quotes',
            'delete supplier quotes',

            // Buyer Quote permissions
            'view buyer quotes',
            'create buyer quotes',
            'update buyer quotes',
            'delete buyer quotes',

            // Buyer Order permissions
            'view buyer orders',
            'create buyer orders',
            'update buyer orders',
            'delete buyer orders',

            // Supplier Order permissions
            'view supplier orders',
            'create supplier orders',
            'update supplier orders',
            'delete supplier orders',

            // Shipment permissions
            'view shipments',
            'create shipments',
            'update shipments',
            'delete shipments',

            // Buyer Invoice permissions
            'view buyer invoices',
            'create buyer invoices',
            'update buyer invoices',
            'delete buyer invoices',

            // Supplier Invoice permissions
            'view supplier invoices',
            'create supplier invoices',
            'update supplier invoices',
            'delete supplier invoices',

            // Payment permissions
            'view payments',
            'create payments',
            'update payments',
            'delete payments',

            // Settings permissions
            'view erp settings',
            'update erp settings',

            // Tag permissions
            'view tags',
            'create tags',
            'update tags',
            'delete tags',

            // Currency permissions
            'view currencies',
            'create currencies',
            'update currencies',
            'delete currencies',

            // Exchange Rate permissions
            'view exchange rates',
            'create exchange rates',
            'update exchange rates',
            'delete exchange rates',

            // Tax Code permissions
            'view tax codes',
            'create tax codes',
            'update tax codes',
            'delete tax codes',

            // Unit of Measure permissions
            'view unit of measures',
            'create unit of measures',
            'update unit of measures',
            'delete unit of measures',

            // Project permissions
            'view projects',
            'create projects',
            'update projects',
            'delete projects',

            // Audit Log permissions
            'view audit logs',
            'export audit logs',

            // Key Account permissions
            'view key accounts',
            'create key accounts',
            'update key accounts',
            'delete key accounts',

            // Quotation Evaluation permissions
            'view quotation evaluations',
            'create quotation evaluations',
            'update quotation evaluations',
            'delete quotation evaluations',

            // Profit and Loss permissions
            'view profit and losses',
            'create profit and losses',
            'update profit and losses',
            'delete profit and losses',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $this->createSuperadminRole($permissions);
        $this->createAdminRole();
        $this->createSalesRole();
        $this->createFinanceRole();
        $this->createViewerRole();
    }

    /**
     * @param  array<string>  $permissions
     */
    private function createSuperadminRole(array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
    }

    private function createAdminRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Full access to core ERP entities
            'view companies', 'create companies', 'update companies', 'delete companies',
            'view buyers', 'create buyers', 'update buyers', 'delete buyers',
            'view suppliers', 'create suppliers', 'update suppliers', 'delete suppliers',
            'view articles', 'create articles', 'update articles', 'delete articles',
            'view requests', 'create requests', 'update requests', 'delete requests',
            'view supplier quotes', 'create supplier quotes', 'update supplier quotes', 'delete supplier quotes',
            'view buyer quotes', 'create buyer quotes', 'update buyer quotes', 'delete buyer quotes',
            'view buyer orders', 'create buyer orders', 'update buyer orders', 'delete buyer orders',
            'view supplier orders', 'create supplier orders', 'update supplier orders', 'delete supplier orders',
            'view shipments', 'create shipments', 'update shipments', 'delete shipments',
            'view buyer invoices', 'create buyer invoices', 'update buyer invoices', 'delete buyer invoices',
            'view supplier invoices', 'create supplier invoices', 'update supplier invoices', 'delete supplier invoices',
            'view payments', 'create payments', 'update payments', 'delete payments',
            'view erp settings', 'update erp settings',
            'view tags', 'create tags', 'update tags', 'delete tags',
            'view currencies', 'create currencies', 'update currencies', 'delete currencies',
            'view exchange rates', 'create exchange rates', 'update exchange rates', 'delete exchange rates',
            'view tax codes', 'create tax codes', 'update tax codes', 'delete tax codes',
            'view unit of measures', 'create unit of measures', 'update unit of measures', 'delete unit of measures',
            'view projects', 'create projects', 'update projects', 'delete projects',
            'view audit logs',
            'view key accounts', 'create key accounts', 'update key accounts', 'delete key accounts',
            'view quotation evaluations', 'create quotation evaluations', 'update quotation evaluations', 'delete quotation evaluations',
            'view profit and losses', 'create profit and losses', 'update profit and losses', 'delete profit and losses',
        ]);
    }

    private function createSalesRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Sales can manage requests and quotes
            'view companies', 'create companies', 'update companies',
            'view buyers', 'create buyers', 'update buyers',
            'view suppliers',
            'view articles',
            'view requests', 'create requests', 'update requests',
            'view supplier quotes', 'create supplier quotes', 'update supplier quotes',
            'view buyer quotes', 'create buyer quotes', 'update buyer quotes',
            'view buyer orders', 'create buyer orders',
            'view supplier orders',
            'view shipments',
            'view buyer invoices',
            'view supplier invoices',
            'view tags',
            'view currencies',
            'view tax codes',
            'view unit of measures',
            'view projects', 'create projects', 'update projects',
            'view key accounts', 'create key accounts',
            'view quotation evaluations', 'create quotation evaluations', 'update quotation evaluations',
            'view profit and losses', 'create profit and losses', 'update profit and losses',
        ]);
    }

    private function createFinanceRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Finance can manage invoices and payments
            'view companies',
            'view buyers',
            'view suppliers',
            'view requests',
            'view buyer quotes',
            'view supplier quotes',
            'view buyer orders',
            'view supplier orders',
            'view shipments',
            'view buyer invoices', 'create buyer invoices', 'update buyer invoices',
            'view supplier invoices', 'create supplier invoices', 'update supplier invoices',
            'view payments', 'create payments', 'update payments',
            'view currencies',
            'view exchange rates', 'create exchange rates', 'update exchange rates',
            'view tax codes',
            'view unit of measures',
            'view audit logs',
        ]);
    }

    private function createViewerRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Viewer has read-only access
            'view companies',
            'view buyers',
            'view suppliers',
            'view articles',
            'view requests',
            'view supplier quotes',
            'view buyer quotes',
            'view buyer orders',
            'view supplier orders',
            'view shipments',
            'view buyer invoices',
            'view supplier invoices',
            'view payments',
            'view tags',
            'view currencies',
            'view exchange rates',
            'view tax codes',
            'view unit of measures',
            'view projects',
        ]);
    }
}

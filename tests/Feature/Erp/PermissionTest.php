<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Ensure permissions are seeded
    $this->artisan('db:seed', ['--class' => 'ErpPermissionSeeder']);
});

test('erp roles are created', function () {
    expect(Role::where('name', 'superadmin')->exists())->toBeTrue()
        ->and(Role::where('name', 'admin')->exists())->toBeTrue()
        ->and(Role::where('name', 'sales')->exists())->toBeTrue()
        ->and(Role::where('name', 'finance')->exists())->toBeTrue()
        ->and(Role::where('name', 'viewer')->exists())->toBeTrue();
});

test('erp permissions are created', function () {
    $expectedPermissions = [
        'view buyers',
        'create buyers',
        'update buyers',
        'delete buyers',
        'view suppliers',
        'view requests',
        'create requests',
        'view buyer quotes',
        'view audit logs',
    ];

    foreach ($expectedPermissions as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue(
            "Permission '{$permission}' should exist"
        );
    }
});

test('superadmin role has all permissions', function () {
    $superadmin = Role::findByName('superadmin');
    $allPermissions = Permission::all();

    expect($superadmin->permissions->count())->toBe($allPermissions->count());
});

test('viewer role has only view permissions', function () {
    $viewer = Role::findByName('viewer');
    $viewerPermissions = $viewer->permissions->pluck('name')->toArray();

    foreach ($viewerPermissions as $permission) {
        expect($permission)->toStartWith('view');
    }
});

test('sales role has limited permissions', function () {
    $sales = Role::findByName('sales');

    expect($sales->hasPermissionTo('view buyers'))->toBeTrue()
        ->and($sales->hasPermissionTo('create buyers'))->toBeTrue()
        ->and($sales->hasPermissionTo('delete buyers'))->toBeFalse()
        ->and($sales->hasPermissionTo('view audit logs'))->toBeFalse();
});

test('finance role can manage invoices', function () {
    $finance = Role::findByName('finance');

    expect($finance->hasPermissionTo('view buyer invoices'))->toBeTrue()
        ->and($finance->hasPermissionTo('create buyer invoices'))->toBeTrue()
        ->and($finance->hasPermissionTo('update buyer invoices'))->toBeTrue()
        ->and($finance->hasPermissionTo('delete buyer invoices'))->toBeFalse();
});

test('user can be assigned role', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $user->assignRole('sales');

    expect($user->hasRole('sales'))->toBeTrue()
        ->and($user->can('view buyers'))->toBeTrue()
        ->and($user->can('delete buyers'))->toBeFalse();
});

test('user can have multiple roles', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $user->assignRole(['sales', 'finance']);

    expect($user->hasRole('sales'))->toBeTrue()
        ->and($user->hasRole('finance'))->toBeTrue()
        ->and($user->can('view buyer invoices'))->toBeTrue()
        ->and($user->can('create requests'))->toBeTrue();
});

test('request list access requires the view requests permission', function () {
    $withPermission = User::factory()->withPersonalTeam()->create();

    $stripped = User::factory()->withPersonalTeam()->create();
    $stripped->syncRoles([]);

    $policy = new \App\Policies\RequestPolicy;

    expect($policy->viewAny($withPermission))->toBeTrue()
        ->and($policy->viewAny($stripped))->toBeFalse();
});

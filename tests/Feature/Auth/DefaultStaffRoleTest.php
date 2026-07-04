<?php

declare(strict_types=1);

use App\Models\CompanyPortalUser;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

it('assigns the default viewer role to every newly created user', function (): void {
    $user = User::factory()->create();

    expect($user->hasRole('viewer'))->toBeTrue();
});

it('keeps the default role inert for portal users: no app panel access', function (): void {
    $team = Team::factory()->create();
    $admin = User::factory()->withPersonalTeam()->create();
    $buyer = \App\Models\Company::factory()->buyer()->for($team)->create();

    $portalUser = User::factory()->create();
    $portalUser->teams()->detach();

    CompanyPortalUser::query()->create([
        'team_id' => $team->getKey(),
        'company_id' => $buyer->getKey(),
        'user_id' => $portalUser->getKey(),
        'invited_by' => $admin->getKey(),
        'is_active' => true,
    ]);

    expect($portalUser->hasRole('viewer'))->toBeTrue()
        ->and($portalUser->refresh()->canAccessPanel(Filament::getPanel('app')))->toBeFalse();
});

it('creates users even when the viewer role has not been seeded yet', function (): void {
    Role::query()->where('name', 'viewer')->delete();
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::factory()->create();

    expect($user->hasRole('viewer'))->toBeTrue();
});

it('backfills the viewer role onto existing role-less users and is idempotent', function (): void {
    $roleless = User::factory()->create();
    $roleless->syncRoles([]);

    $superadmin = User::factory()->create();
    $superadmin->syncRoles(['superadmin']);

    $migration = require database_path('migrations/2026_07_05_120000_assign_default_role_to_roleless_users.php');
    $migration->up();
    $migration->up();

    expect($roleless->refresh()->hasRole('viewer'))->toBeTrue()
        ->and($superadmin->refresh()->hasRole('superadmin'))->toBeTrue()
        ->and($superadmin->hasRole('viewer'))->toBeFalse()
        ->and($superadmin->roles()->count())->toBe(1);
});

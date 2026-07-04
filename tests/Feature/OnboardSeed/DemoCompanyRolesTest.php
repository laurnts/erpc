<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\People;
use App\Models\User;
use Relaticle\OnboardSeed\OnboardSeedManager;

it('seeds no role-less companies for a freshly onboarded team', function (): void {
    $user = User::factory()->withPersonalTeam()->create();

    expect(app(OnboardSeedManager::class)->generateFor($user))->toBeTrue();

    $seeded = Company::withoutGlobalScopes()
        ->where('team_id', $user->personalTeam()->id)
        ->get();

    expect($seeded)->not->toBeEmpty()
        ->and($seeded->every(fn (Company $company): bool => $company->is_buyer || $company->is_supplier))->toBeTrue();
});

it('deletes role-less demo companies via the cleanup migration', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    $roleless = Company::factory()->for($team)->create(['is_buyer' => false, 'is_supplier' => false]);
    $buyer = Company::factory()->buyer()->for($team)->create();

    $migration = require database_path('migrations/2026_07_04_070246_delete_roleless_demo_companies.php');
    $migration->up();
    $migration->up(); // idempotent, and survives the dropped notes/tasks tables

    expect(Company::withoutGlobalScopes()->whereKey($roleless->id)->exists())->toBeFalse()
        ->and(Company::withoutGlobalScopes()->whereKey($buyer->id)->exists())->toBeTrue();
});

it('skips role-less companies that still have people attached', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    $company = Company::factory()->for($team)->create(['is_buyer' => false, 'is_supplier' => false]);
    $person = People::factory()->for($team)->create();
    $company->people()->attach($person);

    $migration = require database_path('migrations/2026_07_04_070246_delete_roleless_demo_companies.php');
    $migration->up();

    expect(Company::withoutGlobalScopes()->whereKey($company->id)->exists())->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\People;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

uses(RefreshDatabase::class);

test('company belongs to team', function () {
    $team = Team::factory()->create();
    $company = Company::factory()->create([
        'team_id' => $team->getKey(),
    ]);

    expect($company->team)->toBeInstanceOf(Team::class)
        ->and($company->team->getKey())->toBe($team->getKey());
});

test('company belongs to creator', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create([
        'creator_id' => $user->getKey(),
    ]);

    expect($company->creator)->toBeInstanceOf(User::class)
        ->and($company->creator->getKey())->toBe($user->getKey());
});

test('company belongs to account owner', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create([
        'account_owner_id' => $user->getKey(),
    ]);

    expect($company->accountOwner)->toBeInstanceOf(User::class)
        ->and($company->accountOwner->getKey())->toBe($user->getKey());
});

test('company has many people', function () {
    $company = Company::factory()->create();
    $person = People::factory()->create();

    // Attach person to company through pivot table
    $company->people()->attach($person->getKey());

    expect($company->people->first())->toBeInstanceOf(People::class)
        ->and($company->people->first()->getKey())->toBe($person->getKey());
});

test('company has logo attribute', function () {
    $company = Company::factory()->create([
        'name' => 'Test Company',
    ]);

    expect($company->logo)->not->toBeNull();
});

test('company uses media library', function () {
    $company = Company::factory()->create();

    expect(class_implements($company))->toContain(HasMedia::class)
        ->and(class_uses_recursive($company))->toContain(InteractsWithMedia::class);
});

test('company uses custom fields', function () {
    $company = Company::factory()->create();

    expect(class_implements($company))->toContain(HasCustomFields::class)
        ->and(class_uses_recursive($company))->toContain(UsesCustomFields::class);
});

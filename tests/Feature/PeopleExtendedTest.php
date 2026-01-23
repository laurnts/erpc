<?php

declare(strict_types=1);

use App\Models\People;
use App\Models\Team;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->user->teams()->attach($this->team);
    actingAs($this->user);
});

test('People can be created with is_key_account flag', function (): void {
    $person = People::factory()->create([
        'team_id' => $this->team->id,
        'is_key_account' => true,
        'creator_id' => $this->user->id,
    ]);

    expect($person->is_key_account)->toBeTrue();
});

test('People can be created without is_key_account flag', function (): void {
    $person = People::factory()->create([
        'team_id' => $this->team->id,
        'is_key_account' => false,
        'creator_id' => $this->user->id,
    ]);

    expect($person->is_key_account)->toBeFalse();
});

test('People can be queried by is_key_account scope', function (): void {
    People::factory()->count(3)->create([
        'team_id' => $this->team->id,
        'is_key_account' => true,
        'creator_id' => $this->user->id,
    ]);

    People::factory()->count(2)->create([
        'team_id' => $this->team->id,
        'is_key_account' => false,
        'creator_id' => $this->user->id,
    ]);

    $keyAccounts = People::where('is_key_account', true)->get();

    expect($keyAccounts)->toHaveCount(3);
});

test('People belongs to team', function (): void {
    $person = People::factory()->create([
        'team_id' => $this->team->id,
        'creator_id' => $this->user->id,
    ]);

    expect($person->team)->toBeInstanceOf(Team::class)
        ->and($person->team_id)->toBe($this->team->id);
});

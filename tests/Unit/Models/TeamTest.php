<?php

declare(strict_types=1);

use App\Data\TeamErpSettings;
use App\Models\Company;
use App\Models\Note;
use App\Models\People;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;

uses(RefreshDatabase::class);

test('team has many people', function () {
    $team = Team::factory()->create();
    $people = People::factory()->create([
        'team_id' => $team->id,
    ]);

    expect($team->people->first())->toBeInstanceOf(People::class)
        ->and($team->people()->firstWhere('id', $people->id)?->id)->toBe($people->id);
});

test('team has many companies', function () {
    $team = Team::factory()->create();
    $company = Company::factory()->create([
        'team_id' => $team->id,
    ]);

    expect($team->companies->first())->toBeInstanceOf(Company::class)
        ->and($team->companies()->firstWhere('id', $company->id)?->id)->toBe($company->id);
});

test('team has many tasks', function () {
    $team = Team::factory()->create();
    $task = Task::factory()->create([
        'team_id' => $team->id,
    ]);

    $teamTask = $team->tasks()->firstWhere('id', $task->id);

    expect($teamTask)->toBeInstanceOf(Task::class)
        ->and($teamTask?->id)->toBe($task->id);
});

test('team has many notes', function () {
    $team = Team::factory()->create();
    $note = Note::factory()->create([
        'team_id' => $team->id,
    ]);

    $teamNote = $team->notes()->firstWhere('id', $note->id);

    expect($teamNote)->toBeInstanceOf(Note::class)
        ->and($teamNote?->id)->toBe($note->id);
});

test('team is personal team', function () {
    $personalTeam = Team::factory()->create([
        'personal_team' => true,
    ]);

    $regularTeam = Team::factory()->create([
        'personal_team' => false,
    ]);

    expect($personalTeam->isPersonalTeam())->toBeTrue()
        ->and($regularTeam->isPersonalTeam())->toBeFalse();
});

test('team has avatar', function () {
    $team = Team::factory()->create([
        'name' => 'Test Team',
    ]);

    expect($team->getFilamentAvatarUrl())->not->toBeNull();
});

test('team uses company logo for avatar when uploaded', function () {
    $team = Team::factory()->create([
        'name' => 'Test Team',
    ]);

    $team->addMedia(base_path('public/favicon.svg'))
        ->preservingOriginal()
        ->toMediaCollection('company_logo');

    expect($team->getCompanyLogoUrl())->not->toBeNull()
        ->and($team->getFilamentAvatarUrl())->toBe($team->getCompanyLogoUrl());
});

test('team email logo prefers company logo over legacy email logo', function () {
    $team = Team::factory()->create();

    $legacyLogo = $team->addMedia(base_path('public/favicon.svg'))
        ->preservingOriginal()
        ->toMediaCollection('email_logo');

    $team->update([
        'erp_settings' => TeamErpSettings::from([
            ...$team->getErpSettings()->toArray(),
            'email_logo_media_id' => (string) $legacyLogo->id,
        ]),
    ]);

    $companyLogo = $team->addMedia(base_path('public/relaticle-logomark.svg'))
        ->preservingOriginal()
        ->toMediaCollection('company_logo');

    expect($team->getEmailLogoUrl())->toBe($companyLogo->getUrl());
});

test('team favicon url returns null when not uploaded', function () {
    $team = Team::factory()->create();

    expect($team->getFaviconUrl())->toBeNull();
});

test('team events are dispatched', function () {
    Event::fake();

    $team = Team::factory()->create([
        'name' => 'Test Team',
    ]);

    $team->update([
        'name' => 'Updated Team',
    ]);

    $team->delete();

    Event::assertDispatched(TeamCreated::class);
    Event::assertDispatched(TeamUpdated::class);
    Event::assertDispatched(TeamDeleted::class);
});

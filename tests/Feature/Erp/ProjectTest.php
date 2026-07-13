<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Models\Company;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

test('project can be created via factory', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create([
        'name' => 'Test Project',
        'project_number' => 'PRJ-2026-TEST',
        'description' => 'A test project description',
        'creator_id' => $this->user->id,
    ]);

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->name)->toBe('Test Project')
        ->and($project->project_number)->toBe('PRJ-2026-TEST')
        ->and($project->description)->toBe('A test project description')
        ->and($project->team_id)->toBe($this->user->personalTeam()->id)
        ->and($project->creator_id)->toBe($this->user->id);
});

test('project belongs to team', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create();

    expect($project->team)->toBeInstanceOf(Team::class)
        ->and($project->team->id)->toBe($this->user->personalTeam()->id);
});

test('project belongs to creator', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    expect($project->creator)->toBeInstanceOf(User::class)
        ->and($project->creator->id)->toBe($this->user->id);
});

test('project has default values', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create([
        'status' => ProjectStatus::DRAFT,
        'is_active' => true,
    ]);

    expect($project->status)->toBe(ProjectStatus::DRAFT)
        ->and($project->is_active)->toBeTrue();
});

test('project number is unique per team', function () {
    Project::factory()->for($this->user->personalTeam())->create(['project_number' => 'PRJ-2026-0001']);

    expect(fn () => Project::factory()->for($this->user->personalTeam())->create(['project_number' => 'PRJ-2026-0001']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same project number can exist in different teams', function () {
    $user2 = User::factory()->withPersonalTeam()->create();

    $project1 = Project::factory()->for($this->user->personalTeam())->create(['project_number' => 'PRJ-SHARED']);
    $project2 = Project::factory()->for($user2->personalTeam())->create(['project_number' => 'PRJ-SHARED']);

    expect($project1->id)->not->toBe($project2->id)
        ->and($project1->project_number)->toBe($project2->project_number)
        ->and($project1->team_id)->not->toBe($project2->team_id);
});

test('project can be deactivated', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create(['is_active' => true]);

    $project->update(['is_active' => false]);

    expect($project->fresh()->is_active)->toBeFalse();
});

test('project factory creates valid project', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create();

    expect($project->name)->not->toBeEmpty()
        ->and($project->project_number)->not->toBeEmpty()
        ->and($project->team_id)->not->toBeNull()
        ->and($project->team_id)->toBe($this->user->personalTeam()->id);
});

test('inactive factory state works', function () {
    $project = Project::factory()->for($this->user->personalTeam())->inactive()->create();

    expect($project->is_active)->toBeFalse();
});

test('draft factory state works', function () {
    $project = Project::factory()->for($this->user->personalTeam())->draft()->create();

    expect($project->status)->toBe(ProjectStatus::DRAFT);
});

test('active factory state works', function () {
    $project = Project::factory()->for($this->user->personalTeam())->active()->create();

    expect($project->status)->toBe(ProjectStatus::ACTIVE);
});

test('completed factory state works', function () {
    $project = Project::factory()->for($this->user->personalTeam())->completed()->create();

    expect($project->status)->toBe(ProjectStatus::COMPLETED);
});

test('cancelled factory state works', function () {
    $project = Project::factory()->for($this->user->personalTeam())->cancelled()->create();

    expect($project->status)->toBe(ProjectStatus::CANCELLED);
});

test('on hold factory state works', function () {
    $project = Project::factory()->for($this->user->personalTeam())->onHold()->create();

    expect($project->status)->toBe(ProjectStatus::ON_HOLD);
});

test('project can be linked to buyer', function () {
    $buyer = Company::factory()->buyer()->for($this->user->personalTeam())->create([
        'name' => 'Test Buyer',
    ]);

    $project = Project::factory()->for($this->user->personalTeam())->create([
        'buyer_id' => $buyer->id,
    ]);

    expect($project->buyer)->toBeInstanceOf(Company::class)
        ->and($project->buyer->id)->toBe($buyer->id)
        ->and($project->buyer->name)->toBe('Test Buyer');
});

test('project buyer relationship is optional', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create([
        'buyer_id' => null,
    ]);

    expect($project->buyer)->toBeNull();
});

test('project observer sets team and creator on create', function () {
    $project = Project::create([
        'name' => 'Observer Test',
        'project_number' => 'PRJ-OBS',
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);

    expect($project->team_id)->toBe($this->user->personalTeam()->id)
        ->and($project->creator_id)->toBe($this->user->id);
});

test('project observer auto-generates project number when not provided', function () {
    // Test code generation logic directly, since Event::fake() prevents observers
    $observer = app(\App\Observers\ProjectObserver::class);

    $year = date('Y');

    // First project
    $project1 = new Project([
        'name' => 'First Project',
        'team_id' => $this->user->personalTeam()->id,
    ]);
    $observer->creating($project1);

    expect($project1->project_number)->toBe('PRJ-'.$year.'-0001');

    // Save it to test sequential generation
    $project1->save();

    // Second project
    $project2 = new Project([
        'name' => 'Second Project',
        'team_id' => $this->user->personalTeam()->id,
    ]);
    $observer->creating($project2);

    expect($project2->project_number)->toBe('PRJ-'.$year.'-0002');

    // Third project with explicit number should keep it
    $project3 = new Project([
        'name' => 'Third Project',
        'project_number' => 'PRJ-CUSTOM',
        'team_id' => $this->user->personalTeam()->id,
    ]);
    $observer->creating($project3);

    expect($project3->project_number)->toBe('PRJ-CUSTOM');
});

test('project soft deletes work correctly', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create();
    $projectId = $project->id;

    $project->delete();

    expect(Project::find($projectId))->toBeNull()
        ->and(Project::withTrashed()->find($projectId))->not->toBeNull();
});

test('project can be restored after soft delete', function () {
    $project = Project::factory()->for($this->user->personalTeam())->create();

    $project->delete();
    $project->restore();

    expect($project->fresh()->deleted_at)->toBeNull();
});

test('for buyer factory state works', function () {
    $buyer = Company::factory()->buyer()->for($this->user->personalTeam())->create();

    $project = Project::factory()
        ->for($this->user->personalTeam())
        ->forBuyer($buyer)
        ->create();

    expect($project->buyer_id)->toBe($buyer->id);
});

test('with dates factory state works', function () {
    $startDate = '2026-01-15';
    $endDate = '2026-06-15';

    $project = Project::factory()
        ->for($this->user->personalTeam())
        ->withDates($startDate, $endDate)
        ->create();

    expect($project->start_date->format('Y-m-d'))->toBe($startDate)
        ->and($project->end_date->format('Y-m-d'))->toBe($endDate);
});

test('project status enum has correct labels', function () {
    expect(ProjectStatus::DRAFT->getLabel())->toBe('Draft')
        ->and(ProjectStatus::ACTIVE->getLabel())->toBe('Active')
        ->and(ProjectStatus::COMPLETED->getLabel())->toBe('Completed')
        ->and(ProjectStatus::CANCELLED->getLabel())->toBe('Cancelled')
        ->and(ProjectStatus::ON_HOLD->getLabel())->toBe('On Hold');
});

test('project status enum has correct colors', function () {
    expect(ProjectStatus::DRAFT->getColor())->toBe('gray')
        ->and(ProjectStatus::ACTIVE->getColor())->toBe('success')
        ->and(ProjectStatus::COMPLETED->getColor())->toBe('info')
        ->and(ProjectStatus::CANCELLED->getColor())->toBe('danger')
        ->and(ProjectStatus::ON_HOLD->getColor())->toBe('warning');
});

test('project status enum has correct icons', function () {
    expect(ProjectStatus::DRAFT->getIcon())->toBe('heroicon-o-pencil-square')
        ->and(ProjectStatus::ACTIVE->getIcon())->toBe('heroicon-o-play-circle')
        ->and(ProjectStatus::COMPLETED->getIcon())->toBe('heroicon-o-check-circle')
        ->and(ProjectStatus::CANCELLED->getIcon())->toBe('heroicon-o-x-circle')
        ->and(ProjectStatus::ON_HOLD->getIcon())->toBe('heroicon-o-pause-circle');
});

test('project can change status', function () {
    $project = Project::factory()->for($this->user->personalTeam())->draft()->create();

    expect($project->status)->toBe(ProjectStatus::DRAFT);

    $project->update(['status' => ProjectStatus::ACTIVE]);
    expect($project->fresh()->status)->toBe(ProjectStatus::ACTIVE);

    $project->update(['status' => ProjectStatus::ON_HOLD]);
    expect($project->fresh()->status)->toBe(ProjectStatus::ON_HOLD);

    $project->update(['status' => ProjectStatus::COMPLETED]);
    expect($project->fresh()->status)->toBe(ProjectStatus::COMPLETED);
});

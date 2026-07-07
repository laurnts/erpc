<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Filament\Resources\EventLogResource\Pages\ListEventLogs;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team = $this->admin->personalTeam();

    actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);
});

it('records an update with actor type, team, causer, and a field diff', function (): void {
    $company = Company::factory()->buyer()->recycle($this->team)->create(['name' => 'Original Co']);

    ActivityLog::query()->delete();

    $company->update(['name' => 'Renamed Co']);

    $activity = ActivityLog::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('updated')
        ->and($activity->actor_type)->toBe(ActorType::Staff)
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->team_id)->toBe($this->team->id)
        ->and($activity->subject_type)->toBe('company')
        ->and($activity->subject_id)->toBe($company->id)
        ->and($activity->properties->get('attributes'))->toMatchArray(['name' => 'Renamed Co'])
        ->and($activity->properties->get('old'))->toMatchArray(['name' => 'Original Co']);
});

it('records creation events with the staff actor', function (): void {
    ActivityLog::query()->delete();

    $company = Company::factory()->buyer()->recycle($this->team)->create(['name' => 'New Co']);

    $activity = ActivityLog::query()
        ->where('subject_type', 'company')
        ->where('subject_id', $company->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_type)->toBe(ActorType::Staff)
        ->and($activity->properties->get('attributes'))->toHaveKey('name', 'New Co');
});

it('skips logging when no audited attribute changes', function (): void {
    $company = Company::factory()->buyer()->recycle($this->team)->create();

    ActivityLog::query()->delete();

    // internal_notes is intentionally not an audited attribute for Company.
    $company->update(['internal_notes' => 'internal only, not audited']);

    expect(ActivityLog::query()->count())->toBe(0);
});

it('renders the event logs page for team admins', function (): void {
    livewire(ListEventLogs::class)->assertOk();
});

it('forbids non-admin members from the event logs page', function (): void {
    $member = User::factory()->create();
    $this->team->users()->attach($member, ['role' => 'editor']);
    $member->forceFill(['current_team_id' => $this->team->id])->save();

    actingAs($member);
    Filament::setTenant($this->team);

    livewire(ListEventLogs::class)->assertForbidden();
});

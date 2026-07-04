<?php

declare(strict_types=1);

use App\Filament\Pages\EditTeam;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

test('the edit team page renders without the duplicate member management sections', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $owner->ownedTeams()->save($team = Team::factory()->make([
        'personal_team' => false,
    ]));

    $this->actingAs($owner);
    Filament::setTenant($team->fresh());

    livewire(EditTeam::class)
        ->assertSuccessful()
        ->assertDontSee('Add Team Member')
        ->assertDontSee('Team Members')
        ->assertSee('Team Name')
        ->assertSee('Delete Team');
});

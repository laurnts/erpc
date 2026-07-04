<?php

declare(strict_types=1);

use App\Actions\Jetstream\RemoveTeamMember;
use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Models\Membership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

test('a member can leave the team via the leave team row action', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->users()->attach($member, ['role' => 'editor']);

    $membership = Membership::query()
        ->where('team_id', $team->id)
        ->where('user_id', $member->id)
        ->firstOrFail();

    $this->actingAs($member);
    Filament::setTenant($team);

    livewire(ListMembers::class)
        ->callTableAction('leaveTeam', $membership)
        ->assertHasNoTableActionErrors();

    expect($team->fresh()->users()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('the team owner may not leave a team they created', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();

    expect(fn () => app(RemoveTeamMember::class)->remove($owner, $team, $owner))
        ->toThrow(ValidationException::class, 'You may not leave a team that you created.');

    expect($team->fresh())->not->toBeNull();
});

test('the leave team action is not visible on another members row', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    $team->users()->attach($memberA, ['role' => 'editor']);
    $team->users()->attach($memberB, ['role' => 'editor']);

    $membershipOfMemberB = Membership::query()
        ->where('team_id', $team->id)
        ->where('user_id', $memberB->id)
        ->firstOrFail();

    $this->actingAs($memberA);
    Filament::setTenant($team);

    livewire(ListMembers::class)
        ->assertTableActionHidden('leaveTeam', $membershipOfMemberB);
});

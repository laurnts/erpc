<?php

declare(strict_types=1);

use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Models\Membership;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

test('the team owner is displayed on the members list with an owner badge', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->users()->attach($member, ['role' => 'editor']);

    $this->actingAs($member);
    Filament::setTenant($team);

    livewire(ListMembers::class)
        ->assertSee($owner->name)
        ->assertSee($owner->email)
        ->assertSee('Owner');

    expect(
        Membership::query()
            ->where('team_id', $team->id)
            ->where('user_id', $owner->id)
            ->first()
    )->toBeNull();
});

<?php

declare(strict_types=1);

use App\Filament\Resources\MemberResource\Pages\ViewMember;
use App\Models\Membership;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

test('editing a member updates role and profile basics but leaves login credentials untouched', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);
    $team->users()->attach($member, ['role' => 'editor']);

    $membership = Membership::query()
        ->where('team_id', $team->id)
        ->where('user_id', $member->id)
        ->firstOrFail();

    $originalEmail = $member->email;
    $originalPasswordHash = $member->password;

    $this->actingAs($owner);
    Filament::setTenant($team);

    livewire(ViewMember::class, ['record' => $membership->getKey()])
        ->callAction('edit', data: [
            'name' => 'Updated Name',
            'role' => 'admin',
        ])
        ->assertHasNoActionErrors();

    $member->refresh();
    $membership->refresh();

    expect($member->name)->toBe('Updated Name')
        ->and($membership->role)->toBe('admin')
        ->and($member->email)->toBe($originalEmail)
        ->and($member->password)->toBe($originalPasswordHash);
});

test('the member edit form does not expose email or password fields', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->users()->attach($member, ['role' => 'editor']);

    $membership = Membership::query()
        ->where('team_id', $team->id)
        ->where('user_id', $member->id)
        ->firstOrFail();

    $this->actingAs($owner);
    Filament::setTenant($team);

    livewire(ViewMember::class, ['record' => $membership->getKey()])
        ->mountAction('edit')
        ->assertSchemaComponentDoesNotExist('email')
        ->assertSchemaComponentDoesNotExist('password');
});

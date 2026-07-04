<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;

it('approves a pending team invitation and adds the member', function (): void {
    $team = Team::factory()->create();
    $invitee = User::factory()->create(['email' => 'new.member@example.com']);
    $team->teamInvitations()->create([
        'email' => 'new.member@example.com',
        'role' => 'editor',
    ]);

    $this->artisan('member-invite:approve', ['email' => 'new.member@example.com'])
        ->assertSuccessful();

    expect($invitee->refresh()->belongsToTeam($team))->toBeTrue()
        ->and($team->teamInvitations()->count())->toBe(0);
});

it('fails when the invited email has no registered user', function (): void {
    $team = Team::factory()->create();
    $team->teamInvitations()->create([
        'email' => 'ghost@example.com',
        'role' => 'editor',
    ]);

    $this->artisan('member-invite:approve', ['email' => 'ghost@example.com'])
        ->assertFailed();

    expect($team->teamInvitations()->count())->toBe(1);
});

it('fails when no pending invitation exists for the email', function (): void {
    $this->artisan('member-invite:approve', ['email' => 'nobody@example.com'])
        ->assertFailed();
});

it('requires --team when the email is invited to multiple teams', function (): void {
    $teamA = Team::factory()->create(['name' => 'Alpha']);
    $teamB = Team::factory()->create(['name' => 'Beta']);
    $invitee = User::factory()->create(['email' => 'busy@example.com']);
    $teamA->teamInvitations()->create(['email' => 'busy@example.com', 'role' => 'editor']);
    $teamB->teamInvitations()->create(['email' => 'busy@example.com', 'role' => 'editor']);

    $this->artisan('member-invite:approve', ['email' => 'busy@example.com'])
        ->assertFailed();

    $this->artisan('member-invite:approve', ['email' => 'busy@example.com', '--team' => 'Beta'])
        ->assertSuccessful();

    expect($invitee->refresh()->belongsToTeam($teamB))->toBeTrue()
        ->and($invitee->belongsToTeam($teamA))->toBeFalse()
        ->and($teamA->teamInvitations()->count())->toBe(1);
});

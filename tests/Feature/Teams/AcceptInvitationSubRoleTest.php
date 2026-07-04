<?php

declare(strict_types=1);

use App\Actions\Jetstream\AddTeamMember;
use App\Enums\CentralPurchasingRole;
use App\Models\Membership;
use App\Models\User;

test('accepting an invitation with a central purchasing sub-role preserves it on the membership', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->currentTeam;
    $invitee = User::factory()->create();

    $team->teamInvitations()->create([
        'email' => $invitee->email,
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::KEY_ACCOUNT->value,
    ]);

    // Mirrors the vendor TeamInvitationController::accept() call sequence, which
    // forwards only `role` and deletes the invitation AFTER add() returns.
    app(AddTeamMember::class)->add($owner, $team, $invitee->email, 'central_purchasing');

    $membership = Membership::where('team_id', $team->id)
        ->where('user_id', $invitee->id)
        ->firstOrFail();

    expect($membership->role)->toBe('central_purchasing')
        ->and($membership->central_purchasing_role)->toBe(CentralPurchasingRole::KEY_ACCOUNT);
});

test('an explicitly passed sub-role wins over the pending invitation sub-role', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->currentTeam;
    $invitee = User::factory()->create();

    $team->teamInvitations()->create([
        'email' => $invitee->email,
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::KEY_ACCOUNT->value,
    ]);

    app(AddTeamMember::class)->add(
        $owner,
        $team,
        $invitee->email,
        'central_purchasing',
        CentralPurchasingRole::FINANCE->value,
    );

    $membership = Membership::where('team_id', $team->id)
        ->where('user_id', $invitee->id)
        ->firstOrFail();

    expect($membership->central_purchasing_role)->toBe(CentralPurchasingRole::FINANCE);
});

test('adding a non central purchasing role member is unaffected by a pending invitation lookup', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->currentTeam;
    $invitee = User::factory()->create();

    $team->teamInvitations()->create([
        'email' => $invitee->email,
        'role' => 'editor',
        'central_purchasing_role' => null,
    ]);

    app(AddTeamMember::class)->add($owner, $team, $invitee->email, 'editor');

    $membership = Membership::where('team_id', $team->id)
        ->where('user_id', $invitee->id)
        ->firstOrFail();

    expect($membership->role)->toBe('editor')
        ->and($membership->central_purchasing_role)->toBeNull();
});

test('adding a central purchasing member with an explicit sub-role and no pending invitation does not error', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->currentTeam;
    $invitee = User::factory()->create();

    app(AddTeamMember::class)->add(
        $owner,
        $team,
        $invitee->email,
        'central_purchasing',
        CentralPurchasingRole::DIRECTOR->value,
    );

    $membership = Membership::where('team_id', $team->id)
        ->where('user_id', $invitee->id)
        ->firstOrFail();

    expect($membership->central_purchasing_role)->toBe(CentralPurchasingRole::DIRECTOR);
});

test('adding a central purchasing member without a sub-role and without a pending invitation fails validation instead of throwing a fatal error', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->currentTeam;
    $invitee = User::factory()->create();

    expect(fn () => app(AddTeamMember::class)->add($owner, $team, $invitee->email, 'central_purchasing'))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

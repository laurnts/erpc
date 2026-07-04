<?php

declare(strict_types=1);

use App\Actions\Teams\UpdateTeamMemberRole;
use App\Enums\CentralPurchasingRole;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Jetstream\Events\TeamMemberUpdated;

test('promoting a member to central purchasing finance sets sub-role and approver', function () {
    Event::fake([TeamMemberUpdated::class]);

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $otherUser = User::factory()->create();

    $team->users()->attach($otherUser, [
        'role' => 'editor',
        'central_purchasing_role' => null,
        'is_approver' => false,
    ]);

    $membership = Membership::where('team_id', $team->id)
        ->where('user_id', $otherUser->id)
        ->firstOrFail();

    app(UpdateTeamMemberRole::class)->execute(
        $team,
        $membership,
        'central_purchasing',
        CentralPurchasingRole::FINANCE->value,
        true,
    );

    $membership->refresh();

    expect($membership->role)->toBe('central_purchasing')
        ->and($membership->central_purchasing_role)->toBe(CentralPurchasingRole::FINANCE)
        ->and($membership->is_approver)->toBeTrue();

    Event::assertDispatched(TeamMemberUpdated::class);
});

test('demoting a central purchasing finance approver to editor clears sub-role and approver', function () {
    Event::fake([TeamMemberUpdated::class]);

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $otherUser = User::factory()->create();

    $team->users()->attach($otherUser, [
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::FINANCE,
        'is_approver' => true,
    ]);

    $membership = Membership::where('team_id', $team->id)
        ->where('user_id', $otherUser->id)
        ->firstOrFail();

    app(UpdateTeamMemberRole::class)->execute(
        $team,
        $membership,
        'editor',
    );

    $membership->refresh();

    expect($membership->role)->toBe('editor')
        ->and($membership->central_purchasing_role)->toBeNull()
        ->and($membership->is_approver)->toBeFalse();

    Event::assertDispatched(TeamMemberUpdated::class);
});

test('switching a finance approver to key account clears approver but keeps central purchasing', function () {
    Event::fake([TeamMemberUpdated::class]);

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $otherUser = User::factory()->create();

    $team->users()->attach($otherUser, [
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::FINANCE,
        'is_approver' => true,
    ]);

    $membership = Membership::where('team_id', $team->id)
        ->where('user_id', $otherUser->id)
        ->firstOrFail();

    app(UpdateTeamMemberRole::class)->execute(
        $team,
        $membership,
        'central_purchasing',
        CentralPurchasingRole::KEY_ACCOUNT->value,
    );

    $membership->refresh();

    expect($membership->role)->toBe('central_purchasing')
        ->and($membership->central_purchasing_role)->toBe(CentralPurchasingRole::KEY_ACCOUNT)
        ->and($membership->is_approver)->toBeFalse();

    Event::assertDispatched(TeamMemberUpdated::class);
});

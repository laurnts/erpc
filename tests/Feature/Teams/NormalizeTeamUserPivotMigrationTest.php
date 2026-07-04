<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;

test('normalizes inconsistent team_user pivot rows and stays idempotent', function () {
    $team = Team::factory()->create();

    $editor = User::factory()->recycle($team)->create();
    $team->users()->attach($editor, [
        'role' => 'editor',
        'central_purchasing_role' => CentralPurchasingRole::KEY_ACCOUNT->value,
        'is_approver' => true,
    ]);

    $keyAccountApprover = User::factory()->recycle($team)->create();
    $team->users()->attach($keyAccountApprover, [
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::KEY_ACCOUNT->value,
        'is_approver' => true,
    ]);

    $financeApprover = User::factory()->recycle($team)->create();
    $team->users()->attach($financeApprover, [
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::FINANCE->value,
        'is_approver' => true,
    ]);

    $nullRole = User::factory()->recycle($team)->create();
    $team->users()->attach($nullRole, [
        'role' => null,
        'central_purchasing_role' => CentralPurchasingRole::DIRECTOR->value,
        'is_approver' => true,
    ]);

    $migration = include database_path('migrations/2026_07_04_045130_normalize_team_user_central_purchasing_columns.php');
    $migration->up();

    $editorRow = Membership::where('team_id', $team->id)->where('user_id', $editor->id)->firstOrFail();
    expect($editorRow->central_purchasing_role)->toBeNull()
        ->and($editorRow->is_approver)->toBeFalse();

    $keyAccountRow = Membership::where('team_id', $team->id)->where('user_id', $keyAccountApprover->id)->firstOrFail();
    expect($keyAccountRow->is_approver)->toBeFalse()
        ->and($keyAccountRow->central_purchasing_role)->toBe(CentralPurchasingRole::KEY_ACCOUNT);

    $financeRow = Membership::where('team_id', $team->id)->where('user_id', $financeApprover->id)->firstOrFail();
    expect($financeRow->role)->toBe('central_purchasing')
        ->and($financeRow->central_purchasing_role)->toBe(CentralPurchasingRole::FINANCE)
        ->and($financeRow->is_approver)->toBeTrue();

    $nullRoleRow = Membership::where('team_id', $team->id)->where('user_id', $nullRole->id)->firstOrFail();
    expect($nullRoleRow->central_purchasing_role)->toBeNull()
        ->and($nullRoleRow->is_approver)->toBeFalse();

    // Idempotence: running the migration again changes nothing further.
    $migration->up();

    $editorRow->refresh();
    $keyAccountRow->refresh();
    $financeRow->refresh();
    $nullRoleRow->refresh();

    expect($editorRow->central_purchasing_role)->toBeNull()
        ->and($editorRow->is_approver)->toBeFalse()
        ->and($keyAccountRow->is_approver)->toBeFalse()
        ->and($keyAccountRow->central_purchasing_role)->toBe(CentralPurchasingRole::KEY_ACCOUNT)
        ->and($financeRow->role)->toBe('central_purchasing')
        ->and($financeRow->central_purchasing_role)->toBe(CentralPurchasingRole::FINANCE)
        ->and($financeRow->is_approver)->toBeTrue()
        ->and($nullRoleRow->central_purchasing_role)->toBeNull()
        ->and($nullRoleRow->is_approver)->toBeFalse();
});

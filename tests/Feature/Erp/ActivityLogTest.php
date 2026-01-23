<?php

declare(strict_types=1);

use App\Models\Company;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('activity log table exists', function () {
    // Check if we can query the activity log table
    $count = Activity::count();

    expect($count)->toBeGreaterThanOrEqual(0);
});

test('activity can be logged manually', function () {
    $user = User::factory()->withPersonalTeam()->create();

    activity()
        ->causedBy($user)
        ->withProperties(['custom' => 'data'])
        ->log('Test activity logged');

    $activity = Activity::latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Test activity logged')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->get('custom'))->toBe('data');
});

test('activity log captures subject', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    activity()
        ->causedBy($user)
        ->performedOn($team)
        ->log('Action on team');

    $activity = Activity::latest()->first();

    expect($activity->subject_type)->toBe('team')
        ->and($activity->subject_id)->toBe($team->id);
});

test('activity log can have event name', function () {
    activity()
        ->event('created')
        ->log('Entity created');

    $activity = Activity::latest()->first();

    expect($activity->event)->toBe('created');
});

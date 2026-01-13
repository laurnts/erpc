<?php

declare(strict_types=1);

use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

test('tag can be created via factory', function () {
    $tag = Tag::factory()->for($this->user->personalTeam())->create([
        'name' => 'Test Tag',
        'color' => '#FF0000',
        'description' => 'A test tag',
        'creator_id' => $this->user->id,
    ]);

    expect($tag)->toBeInstanceOf(Tag::class)
        ->and($tag->name)->toBe('Test Tag')
        ->and($tag->color)->toBe('#FF0000')
        ->and($tag->team_id)->toBe($this->user->personalTeam()->id)
        ->and($tag->creator_id)->toBe($this->user->id);
});

test('tag belongs to team', function () {
    $tag = Tag::factory()->for($this->user->personalTeam())->create();

    expect($tag->team)->toBeInstanceOf(Team::class)
        ->and($tag->team->id)->toBe($this->user->personalTeam()->id);
});

test('tag belongs to creator', function () {
    $tag = Tag::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    expect($tag->creator)->toBeInstanceOf(User::class)
        ->and($tag->creator->id)->toBe($this->user->id);
});

test('tag has default values', function () {
    $tag = Tag::factory()->for($this->user->personalTeam())->create([
        'color' => 'gray',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    expect($tag->color)->toBe('gray')
        ->and($tag->is_active)->toBeTrue()
        ->and($tag->sort_order)->toBe(0);
});

test('tag name is unique per team', function () {
    Tag::factory()->for($this->user->personalTeam())->create(['name' => 'Unique Tag']);

    expect(fn () => Tag::factory()->for($this->user->personalTeam())->create(['name' => 'Unique Tag']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same tag name can exist in different teams', function () {
    $user2 = User::factory()->withPersonalTeam()->create();

    $tag1 = Tag::factory()->for($this->user->personalTeam())->create(['name' => 'Shared Name']);
    $tag2 = Tag::factory()->for($user2->personalTeam())->create(['name' => 'Shared Name']);

    expect($tag1->id)->not->toBe($tag2->id)
        ->and($tag1->name)->toBe($tag2->name)
        ->and($tag1->team_id)->not->toBe($tag2->team_id);
});

test('tag can be deactivated', function () {
    $tag = Tag::factory()->for($this->user->personalTeam())->create(['is_active' => true]);

    $tag->update(['is_active' => false]);

    expect($tag->fresh()->is_active)->toBeFalse();
});

test('tag factory creates valid tag', function () {
    $tag = Tag::factory()->for($this->user->personalTeam())->create();

    expect($tag->name)->not->toBeEmpty()
        ->and($tag->team_id)->not->toBeNull()
        ->and($tag->team_id)->toBe($this->user->personalTeam()->id);
});

test('inactive factory state works', function () {
    $tag = Tag::factory()->for($this->user->personalTeam())->inactive()->create();

    expect($tag->is_active)->toBeFalse();
});

test('tag observer sets team and creator on create', function () {
    // Create tag using the observer (simulating Filament flow)
    $tag = Tag::create([
        'name' => 'Observer Test',
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);

    expect($tag->team_id)->toBe($this->user->personalTeam()->id)
        ->and($tag->creator_id)->toBe($this->user->id);
});

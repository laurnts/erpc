<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->user->personalTeam());
});

it('has no Workspace navigation group', function (): void {
    $groups = collect(Filament::getPanel('app')->getNavigation())
        ->map(fn ($group): ?string => $group->getLabel())
        ->filter();

    expect($groups)->not->toContain('Workspace');
});

it('lists the former Workspace items under Master Data', function (): void {
    $masterData = collect(Filament::getPanel('app')->getNavigation())
        ->first(fn ($group): bool => $group->getLabel() === 'Master Data');

    expect($masterData)->not->toBeNull();

    $labels = collect($masterData->getItems())
        ->map(fn ($item): string => (string) $item->getLabel())
        ->values();

    foreach (['Buyers', 'Suppliers', 'Articles', 'People', 'Notes', 'Tasks'] as $expected) {
        expect($labels)->toContain($expected);
    }
});

it('does not register a Companies navigation item', function (): void {
    $labels = collect(Filament::getPanel('app')->getNavigation())
        ->flatMap(fn ($group) => collect($group->getItems())->map(fn ($item): string => (string) $item->getLabel()));

    expect($labels)->not->toContain('Companies');
});

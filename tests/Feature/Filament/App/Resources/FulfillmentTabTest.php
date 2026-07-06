<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\AcceptanceReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager;
use App\Models\Company;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationGroup;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();
    $buyer = Company::factory()->buyer()->for($this->team)->create();
    $this->makeRequest = fn (): Request => Request::factory()->for($this->team)->recycle($buyer)->create();
});

function fulfillmentGroup(): RelationGroup
{
    $group = collect(app(ViewRequest::class)->getRelationManagers())
        ->first(fn ($m): bool => $m instanceof RelationGroup && $m->getLabel() === 'Fulfillment');

    expect($group)->toBeInstanceOf(RelationGroup::class);

    return $group;
}

/** @return array<string> */
function visibleFulfillmentManagers(Request $request): array
{
    return array_values(fulfillmentGroup()->ownerRecord($request)->pageClass(ViewRequest::class)->getManagers());
}

it('registers a Fulfillment group at index 6', function (): void {
    $managers = app(ViewRequest::class)->getRelationManagers();

    expect($managers[6])->toBeInstanceOf(RelationGroup::class)
        ->and($managers[6]->getLabel())->toBe('Fulfillment');
});

it('shows only the goods channel for a goods-only request', function (): void {
    $request = ($this->makeRequest)();
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::GOODS]);

    expect(visibleFulfillmentManagers($request))
        ->toContain(ShipmentsRelationManager::class)
        ->not->toContain(AcceptanceReportsRelationManager::class);
});

it('shows only the services channel for a services-only request', function (): void {
    $request = ($this->makeRequest)();
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::SERVICE]);

    expect(visibleFulfillmentManagers($request))
        ->toContain(AcceptanceReportsRelationManager::class)
        ->not->toContain(ShipmentsRelationManager::class);
});

it('shows both channels for a mixed request', function (): void {
    $request = ($this->makeRequest)();
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::GOODS]);
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::SERVICE]);

    expect(visibleFulfillmentManagers($request))
        ->toContain(ShipmentsRelationManager::class)
        ->toContain(AcceptanceReportsRelationManager::class);
});

it('renders the view page with a Fulfillment tab', function (): void {
    $request = ($this->makeRequest)();
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::GOODS]);

    Livewire\Livewire::test(ViewRequest::class, ['record' => $request->getKey()])
        ->assertOk()
        ->assertSee('Fulfillment');
});

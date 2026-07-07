<?php

declare(strict_types=1);

use App\Filament\Resources\BuyerResource\RelationManagers\PortalUsersRelationManager;
use App\Filament\Resources\SupplierResource\Pages\ViewSupplier;
use App\Filament\Resources\SupplierResource\RelationManagers\ArticlesRelationManager;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->user->assignRole('superadmin');
    $this->actingAs($this->user);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->user->personalTeam());

    $this->supplier = Company::factory()->supplier()->for($this->user->personalTeam())->create();
});

it('renders the supplier view page', function (): void {
    livewire(ViewSupplier::class, ['record' => $this->supplier->getKey()])
        ->assertOk();
});

it('mounts the edit slide-over action on the supplier view page', function (): void {
    livewire(ViewSupplier::class, ['record' => $this->supplier->getKey()])
        ->mountAction('edit')
        ->assertOk();
});

it('renders relation managers on the supplier view page', function (): void {
    livewire(ArticlesRelationManager::class, [
        'ownerRecord' => $this->supplier,
        'pageClass' => ViewSupplier::class,
    ])->assertOk();

    livewire(PortalUsersRelationManager::class, [
        'ownerRecord' => $this->supplier,
        'pageClass' => ViewSupplier::class,
    ])->assertOk();
});

it('loads the supplier view page over HTTP', function (): void {
    $url = App\Filament\Resources\SupplierResource::getUrl(
        'view',
        ['record' => $this->supplier],
        panel: 'app',
        tenant: $this->user->personalTeam(),
    );

    $this->get($url)
        ->assertOk()
        ->assertSee($this->supplier->name);
});

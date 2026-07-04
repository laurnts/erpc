<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

it('can render the index page', function (): void {
    livewire(App\Filament\Resources\SupplierResource\Pages\ListSuppliers::class)
        ->assertOk();
});

it('only lists companies flagged as suppliers', function (): void {
    $suppliers = App\Models\Company::factory(2)->supplier()->for($this->user->personalTeam())->create();
    $buyers = App\Models\Company::factory(3)->buyer()->for($this->user->personalTeam())->create();

    livewire(App\Filament\Resources\SupplierResource\Pages\ListSuppliers::class)
        ->assertCanSeeTableRecords($suppliers)
        ->assertCanNotSeeTableRecords($buyers)
        ->assertCountTableRecords(2);
});

it('creates suppliers with the supplier role set', function (): void {
    livewire(App\Filament\Resources\SupplierResource\Pages\CreateSupplier::class)
        ->fillForm([
            'name' => 'MotorCorp Indonesia',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $supplier = App\Models\Company::query()->where('name', 'MotorCorp Indonesia')->sole();

    expect($supplier->is_supplier)->toBeTrue()
        ->and($supplier->is_buyer)->toBeFalse();
});

it('creates a dual-role company when Also a Buyer is checked', function (): void {
    livewire(App\Filament\Resources\SupplierResource\Pages\CreateSupplier::class)
        ->fillForm([
            'name' => 'Both Ways Trading',
            'is_buyer' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $company = App\Models\Company::query()->where('name', 'Both Ways Trading')->sole();

    expect($company->is_supplier)->toBeTrue()
        ->and($company->is_buyer)->toBeTrue();
});

it('shows shared field edits from the supplier view in the buyers list', function (): void {
    $dual = App\Models\Company::factory()->buyerAndSupplier()->for($this->user->personalTeam())->create([
        'name' => 'Original Name',
    ]);

    livewire(App\Filament\Resources\SupplierResource\Pages\ViewSupplier::class, ['record' => $dual->getKey()])
        ->callAction('edit', data: ['name' => 'Renamed Trading Co'])
        ->assertHasNoActionErrors();

    expect($dual->refresh()->name)->toBe('Renamed Trading Co');

    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertCanSeeTableRecords([$dual]);
});

<?php

declare(strict_types=1);

use App\Enums\PortalType;
use App\Models\CompanyPortalUser;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

it('can render the index page', function (): void {
    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertOk();
});

it('can render the view page', function (): void {
    $record = App\Models\Company::factory()->buyer()->for($this->user->personalTeam())->create();

    livewire(App\Filament\Resources\BuyerResource\Pages\ViewBuyer::class, ['record' => $record->getKey()])
        ->assertOk();
});

it('shows invite portal user action when buyer has no active portal access', function (): void {
    $record = App\Models\Company::factory()->buyer()->for($this->user->personalTeam())->create();

    livewire(App\Filament\Resources\BuyerResource\Pages\ViewBuyer::class, ['record' => $record->getKey()])
        ->assertActionVisible('invitePortalUser');
});

it('hides invite portal user action when buyer already has active portal access', function (): void {
    $record = App\Models\Company::factory()->buyer()->for($this->user->personalTeam())->create();
    $portalUser = User::factory()->create();

    CompanyPortalUser::query()->create([
        'team_id' => $this->user->personalTeam()->getKey(),
        'company_id' => $record->getKey(),
        'user_id' => $portalUser->getKey(),
        'portal' => PortalType::Buyer,
        'invited_by' => $this->user->getKey(),
        'is_active' => true,
    ]);

    livewire(App\Filament\Resources\BuyerResource\Pages\ViewBuyer::class, ['record' => $record->getKey()])
        ->assertActionHidden('invitePortalUser');
});

it('shows invite portal user action when buyer only has a pending invitation', function (): void {
    $record = App\Models\Company::factory()->buyer()->for($this->user->personalTeam())->create();

    CompanyPortalUser::query()->create([
        'team_id' => $this->user->personalTeam()->getKey(),
        'company_id' => $record->getKey(),
        'user_id' => null,
        'portal' => PortalType::Buyer,
        'invited_by' => $this->user->getKey(),
        'is_active' => false,
        'invited_name' => 'Pending Person',
        'invited_email' => 'pending@portal.test',
    ]);

    livewire(App\Filament\Resources\BuyerResource\Pages\ViewBuyer::class, ['record' => $record->getKey()])
        ->assertActionVisible('invitePortalUser');
});

it('can render `:dataset` column', function (string $column): void {
    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertCanRenderTableColumn($column);
})->with(['logo', 'code', 'name', 'country', 'credit_limit', 'available_credit', 'is_on_hold', 'is_active', 'updated_at']);

it('only lists companies flagged as buyers', function (): void {
    $buyers = App\Models\Company::factory(2)->buyer()->for($this->user->personalTeam())->create();
    $suppliers = App\Models\Company::factory(3)->supplier()->for($this->user->personalTeam())->create();

    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertCanSeeTableRecords($buyers)
        ->assertCanNotSeeTableRecords($suppliers)
        ->assertCountTableRecords(2);
});

it('lists dual-role companies in both buyers and suppliers', function (): void {
    $dual = App\Models\Company::factory()->buyerAndSupplier()->for($this->user->personalTeam())->create();

    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertCanSeeTableRecords([$dual]);

    livewire(App\Filament\Resources\SupplierResource\Pages\ListSuppliers::class)
        ->assertCanSeeTableRecords([$dual]);
});

it('creates buyers with the buyer role set', function (): void {
    livewire(App\Filament\Resources\BuyerResource\Pages\CreateBuyer::class)
        ->fillForm([
            'name' => 'GlobalTrade Industries',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $buyer = App\Models\Company::query()->where('name', 'GlobalTrade Industries')->sole();

    expect($buyer->is_buyer)->toBeTrue()
        ->and($buyer->is_supplier)->toBeFalse();
});

it('creates a dual-role company when Also a Supplier is checked', function (): void {
    livewire(App\Filament\Resources\BuyerResource\Pages\CreateBuyer::class)
        ->fillForm([
            'name' => 'Both Ways Trading',
            'is_supplier' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $company = App\Models\Company::query()->where('name', 'Both Ways Trading')->sole();

    expect($company->is_buyer)->toBeTrue()
        ->and($company->is_supplier)->toBeTrue();
});

it('scopes the account owner select to team members and the owner only', function (): void {
    $teamMember = App\Models\User::factory()->create(['name' => 'Zelda TeamMember']);
    $this->user->personalTeam()->users()->attach($teamMember, ['role' => 'member']);

    $outsider = App\Models\User::factory()->withPersonalTeam()->create(['name' => 'Oscar Outsider']);

    livewire(App\Filament\Resources\BuyerResource\Pages\CreateBuyer::class)
        ->assertFormFieldExists('account_owner_id', function (\Filament\Forms\Components\Select $field) use ($teamMember, $outsider): bool {
            $options = $field->getOptions();

            return array_key_exists($this->user->id, $options)      // team owner
                && array_key_exists($teamMember->id, $options)      // team member
                && ! array_key_exists($outsider->id, $options);     // user from another team
        });
});

it('cannot display trashed records by default', function (): void {
    $records = App\Models\Company::factory()->buyer()->count(4)->for($this->user->personalTeam())->create();
    $trashedRecords = App\Models\Company::factory()->buyer()->trashed()->count(6)->for($this->user->personalTeam())->create();

    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertCanSeeTableRecords($records)
        ->assertCanNotSeeTableRecords($trashedRecords)
        ->assertCountTableRecords(4);
});

it('can paginate records', function (): void {
    $records = App\Models\Company::factory(20)->buyer()->for($this->user->personalTeam())->create();

    // Fetch records with the same sort order as the table (created_at DESC)
    $sortedRecords = App\Models\Company::query()
        ->whereIn('id', $records->pluck('id'))
        ->orderBy('created_at', 'desc')
        ->get();

    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertCanSeeTableRecords($sortedRecords->take(10), inOrder: true)
        ->call('gotoPage', 2)
        ->assertCanSeeTableRecords($sortedRecords->skip(10)->take(10), inOrder: true);
});

it('can bulk delete records', function (): void {
    $records = App\Models\Company::factory(5)->buyer()->for($this->user->personalTeam())->create();

    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertCanSeeTableRecords($records)
        ->selectTableRecords($records)
        // NOTE: Using direct action array instead of TestAction::make()->bulk()
        // because TestAction triggers unnecessary form building during bulk actions
        ->callAction([['name' => 'delete', 'context' => ['table' => true, 'bulk' => true]]])
        ->assertNotified()
        ->assertCanNotSeeTableRecords($records);

    $this->assertSoftDeleted($records);
});

it('has `:dataset` filter', function (string $filter): void {
    livewire(App\Filament\Resources\BuyerResource\Pages\ListBuyers::class)
        ->assertTableFilterExists($filter);
})->with(['creation_source', 'trashed']);

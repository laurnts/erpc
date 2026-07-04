<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

/**
 * Mounting a list page and running a search forces the search SQL to execute.
 * If a column was made ->searchable() but is virtual/computed (not a real DB
 * column), this throws "column does not exist" — even against an empty table.
 */
test('list page search executes without SQL errors: :dataset', function (string $page): void {
    livewire($page)
        ->searchTable('zzqzzq')
        ->assertOk();
})->with([
    App\Filament\Resources\BuyerResource\Pages\ListBuyers::class,
    App\Filament\Resources\BuyerOrderResource\Pages\ListBuyerOrders::class,
    App\Filament\Resources\BuyerQuoteResource\Pages\ListBuyerQuotes::class,
    App\Filament\Resources\SupplierOrderResource\Pages\ListSupplierOrders::class,
    App\Filament\Resources\SupplierOrderApprovals\Pages\ListSupplierOrderApprovals::class,
    App\Filament\Resources\SupplierQuoteResource\Pages\ListSupplierQuotes::class,
    App\Filament\Resources\SupplierResource\Pages\ListSuppliers::class,
    App\Filament\Resources\BuyerCreditLimitOverviewResource\Pages\ListBuyerCreditLimits::class,
    App\Filament\Resources\BuyerCreditLimitRequestResource\Pages\ListCreditLimitRequests::class,
    App\Filament\Resources\CurrencyResource\Pages\ListCurrencies::class,
    App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates::class,
    App\Filament\Resources\ExchangeRateResource\Pages\ListExchangeRates::class,
    App\Filament\Resources\ProfitAndLossResource\Pages\ListProfitAndLosses::class,
    App\Filament\Resources\QuotationEvaluationResource\Pages\ListQuotationEvaluations::class,
    App\Filament\Resources\TaxCodeResource\Pages\ListTaxCodes::class,
    App\Filament\Resources\UnitOfMeasureResource\Pages\ListUnitOfMeasures::class,
    App\Filament\Resources\PeopleResource\Pages\ListPeople::class,
    App\Filament\Resources\ProjectResource\Pages\ListProjects::class,
    App\Filament\Resources\TagResource\Pages\ListTags::class,
    App\Filament\Resources\MemberResource\Pages\ListMembers::class,
    App\Filament\Resources\NoteResource\Pages\ManageNotes::class,
    App\Filament\Resources\TaskResource\Pages\ManageTasks::class,
]);

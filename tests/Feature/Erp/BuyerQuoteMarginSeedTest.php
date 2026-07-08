<?php

declare(strict_types=1);

use App\Data\TeamErpSettings;
use App\Enums\BuyerQuoteCreationMode;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;
use App\Services\Erp\Financial\MarginConvention;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/**
 * Drives the BuyerQuotesRelationManager's "New buyer quote" CreateAction with the
 * items Repeater deliberately forced back to empty, so the record is created with
 * zero items and the "if items weren't created by the Repeater, create them
 * manually" fallback in the action's after() hook performs the seeding. This is
 * one of the three sites that used to compute unit price the legacy cost-based
 * way (cost * (1 + margin/100)) instead of the canonical sell-based
 * MarginConvention::netUnitPrice().
 */
function seedBuyerQuoteItemFromSupplierQuote(
    Team $team,
    Request $request,
    Company $supplier,
    Currency $currency,
    float $costPrice,
): BuyerQuoteItem {
    $requestItem = RequestItem::factory()->recycle($request)->withQuantity(1)->create();

    $supplierQuote = SupplierQuote::factory()
        ->recycle($team)
        ->recycle($request)
        ->forSupplier($supplier)
        ->withCurrency($currency)
        ->selected()
        ->create(['obtained' => true]);

    SupplierQuoteItem::factory()
        ->forSupplierQuote($supplierQuote)
        ->forRequestItem($requestItem)
        ->withPricing(quantity: 1, unitPrice: $costPrice, taxRate: 0)
        ->create(['is_selected' => true]);

    livewire(BuyerQuotesRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->callAction(
            TestAction::make(CreateAction::class)->table(),
            data: [
                'creation_mode' => BuyerQuoteCreationMode::PER_SUPPLIER->value,
                'supplier_quote_id' => $supplierQuote->getKey(),
                // Setting supplier_quote_id reactively rebuilds `items` via the
                // (already correct) buildFormDataFromSupplierQuotes() path; force it
                // back to empty here so the Repeater relationship saves zero items
                // and the record falls into the manual-seeding fallback under test.
                'items' => [],
            ],
        )
        ->assertHasNoActionErrors();

    $buyerQuote = BuyerQuote::query()
        ->where('request_id', $request->getKey())
        ->latest('id')
        ->firstOrFail();

    return $buyerQuote->items()->firstOrFail();
}

beforeEach(function (): void {
    $this->team = Team::factory()->create([
        'erp_settings' => new TeamErpSettings(
            default_margin_percent: 3.0,
            default_currency: 'USD',
        ),
    ]);
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

    TaxCode::factory()->recycle($this->team)->create([
        'rate' => 0,
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->artisan('db:seed', ['--class' => 'ErpPermissionSeeder']);
    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);
});

describe('Buyer quote fallback seeding uses the sell-based margin convention', function (): void {
    it('seeds a unit price whose on-selling margin matches the target margin within rounding', function (): void {
        $item = seedBuyerQuoteItemFromSupplierQuote($this->team, $this->request, $this->supplier, $this->currency, 10000.0);

        // round(10000 / (1 - 0.03), 0) = round(10309.278...) = 10309
        $expectedUnitPrice = round(MarginConvention::netUnitPrice(10000.0, 3.0), 0);
        expect($expectedUnitPrice)->toBe(10309.0);

        $unitPriceExcTax = (float) $item->unit_price_exc_tax;
        expect($unitPriceExcTax)->toBe($expectedUnitPrice)
            ->and((float) $item->cost_price)->toBe(10000.0);

        // Sell-based margin at the seeded price should read back ~3.0%, not the
        // ~2.91% that the legacy cost-based formula (cost * 1.03 = 10300) would
        // have produced.
        $sellMargin = MarginConvention::marginPercent(10000.0, $unitPriceExcTax);
        expect($sellMargin)->toBeGreaterThan(2.99)
            ->and($sellMargin)->toBeLessThan(3.0);
        expect(round($sellMargin, 1))->toBe(3.0);

        // The persisted margin_percent must be the same sell-based figure —
        // not the cost-based ~3.09 the legacy read-back formula produced.
        expect((float) $item->margin_percent)->toBe(round($sellMargin, 4));
    });

    it('seeds a unit price equal to cost when the target margin is zero', function (): void {
        $this->team->update([
            'erp_settings' => new TeamErpSettings(
                default_margin_percent: 0.0,
                default_currency: 'USD',
            ),
        ]);

        $item = seedBuyerQuoteItemFromSupplierQuote($this->team, $this->request, $this->supplier, $this->currency, 10000.0);

        expect((float) $item->unit_price_exc_tax)->toBe(10000.0)
            ->and((float) $item->cost_price)->toBe(10000.0);
    });
});

<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Livewire\QuotationEvaluationForm;
use App\Models\Company;
use App\Models\Currency;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->personalTeam();
    $this->actingAs($this->user);
    Filament::setTenant($this->team);

    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $this->request = Request::factory()
        ->for($this->team)
        ->recycle($this->buyer)
        ->create(['creator_id' => $this->user->getKey()]);
});

/**
 * Build a supplier quote priced with a single line for the given request item.
 * Quantity is fixed at 1 and tax at 0 so the unit price equals the line total,
 * keeping the arithmetic in the test easy to follow.
 */
function makeSingleLineQuote(Team $team, Request $request, Company $supplier, Currency $currency, RequestItem $requestItem, float $lineTotal): SupplierQuote
{
    $quote = SupplierQuote::factory()
        ->recycle($team)
        ->forRequest($request)
        ->forSupplier($supplier)
        ->withCurrency($currency)
        ->create();

    SupplierQuoteItem::factory()
        ->forSupplierQuote($quote)
        ->forRequestItem($requestItem)
        ->withPricing(1, $lineTotal)
        ->create();

    return $quote;
}

/**
 * Add a second priced line to an existing supplier quote.
 */
function addQuoteLine(SupplierQuote $quote, RequestItem $requestItem, float $lineTotal): SupplierQuoteItem
{
    return SupplierQuoteItem::factory()
        ->forSupplierQuote($quote)
        ->forRequestItem($requestItem)
        ->withPricing(1, $lineTotal)
        ->create();
}

describe('Goods-only quote ranking on mixed requests', function (): void {
    it('ranks the supplier cheaper on goods first even when its overall total is higher', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->withQuantity(1)->create([
            'item_type' => ItemType::GOODS,
        ]);
        $serviceItem = RequestItem::factory()->recycle($this->request)->withQuantity(1)->create([
            'item_type' => ItemType::SERVICE,
        ]);

        $supplierA = Company::factory()->supplier()->recycle($this->team)->create(['name' => 'Supplier A']);
        $supplierB = Company::factory()->supplier()->recycle($this->team)->create(['name' => 'Supplier B']);

        // Supplier A: cheap on goods (100), expensive on services (1000) -> total 1100
        $quoteA = makeSingleLineQuote($this->team, $this->request, $supplierA, $this->currency, $goodsItem, 100.0);
        addQuoteLine($quoteA, $serviceItem, 1000.0);

        // Supplier B: expensive on goods (500), cheap on services (100) -> total 600
        $quoteB = makeSingleLineQuote($this->team, $this->request, $supplierB, $this->currency, $goodsItem, 500.0);
        addQuoteLine($quoteB, $serviceItem, 100.0);

        // Sanity check: total_base ordering alone would rank B first (600 < 1100).
        expect((float) $quoteB->refresh()->total_base)->toBeLessThan((float) $quoteA->refresh()->total_base);

        livewire(QuotationEvaluationForm::class, ['request' => $this->request])
            ->call('save')
            ->assertHasNoErrors();

        $qe = QuotationEvaluation::query()->where('request_id', $this->request->getKey())->firstOrFail();
        $suppliers = $qe->getSuppliers();

        expect($qe->getRequestInfo()['is_mixed'] ?? null)->toBeTrue()
            ->and($suppliers)->toHaveCount(2)
            ->and($suppliers[0]['name'])->toBe('Supplier A')
            ->and((float) $suppliers[0]['goods_total'])->toBe(100.0)
            ->and((float) $suppliers[0]['grand_total'])->toBe(1100.0)
            ->and($suppliers[1]['name'])->toBe('Supplier B')
            ->and((float) $suppliers[1]['goods_total'])->toBe(500.0)
            ->and((float) $suppliers[1]['grand_total'])->toBe(600.0);

        // Rendered output (view-page infolist) shows both the goods subtotal and full total per supplier.
        $html = view('filament.infolists.components.qe-item-comparison', [
            'getState' => fn (): array => $qe->data,
        ])->render();

        expect($html)->toContain('Goods Total')
            ->and($html)->toContain('100.00')
            ->and($html)->toContain('1,100.00')
            ->and($html)->toContain('500.00')
            ->and($html)->toContain('600.00');
    });

    it('leaves ranking and display unchanged on a goods-only request', function (): void {
        $goodsItem = RequestItem::factory()->recycle($this->request)->withQuantity(1)->create([
            'item_type' => ItemType::GOODS,
        ]);

        $supplierA = Company::factory()->supplier()->recycle($this->team)->create(['name' => 'Supplier A']);
        $supplierB = Company::factory()->supplier()->recycle($this->team)->create(['name' => 'Supplier B']);

        // Supplier B is cheaper overall (and on goods, since goods is the only channel).
        $quoteA = makeSingleLineQuote($this->team, $this->request, $supplierA, $this->currency, $goodsItem, 200.0);
        $quoteB = makeSingleLineQuote($this->team, $this->request, $supplierB, $this->currency, $goodsItem, 100.0);

        livewire(QuotationEvaluationForm::class, ['request' => $this->request])
            ->call('save')
            ->assertHasNoErrors();

        $qe = QuotationEvaluation::query()->where('request_id', $this->request->getKey())->firstOrFail();
        $suppliers = $qe->getSuppliers();

        // Ordering matches the plain total_base ordering (ascending).
        expect($qe->getRequestInfo()['is_mixed'] ?? null)->toBeFalse()
            ->and($suppliers)->toHaveCount(2)
            ->and($suppliers[0]['name'])->toBe('Supplier B')
            ->and((float) $suppliers[0]['grand_total'])->toBe(100.0)
            ->and($suppliers[1]['name'])->toBe('Supplier A')
            ->and((float) $suppliers[1]['grand_total'])->toBe(200.0);

        // Display is unchanged: no extra "Goods Total" row for single-type requests.
        $html = view('filament.infolists.components.qe-item-comparison', [
            'getState' => fn (): array => $qe->data,
        ])->render();

        expect($html)->not->toContain('Goods Total');
    });
});

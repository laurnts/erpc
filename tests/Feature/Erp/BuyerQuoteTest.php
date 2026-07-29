<?php

declare(strict_types=1);

use App\Enums\BuyerQuoteStatus;
use App\Enums\RequestStage;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteExtension;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->actingAs($this->user);
});

describe('BuyerQuote Model', function (): void {
    it('can create a buyer quote with required fields', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create([
                'status' => BuyerQuoteStatus::DRAFT,
            ]);

        expect($quote)->toBeInstanceOf(BuyerQuote::class)
            ->and($quote->status)->toBe(BuyerQuoteStatus::DRAFT)
            ->and($quote->version)->toBe(1);
    });

    it('generates quote number on creation', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        // Sequence is a factory fake (see BuyerQuoteFactory), not the real
        // allocator, so it is not constrained to 4 digits like the real format.
        expect($quote->quote_number)->toMatch('/^BQ-\d{4}-\d+$/');
    });

    it('defaults to draft status', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($quote->status)->toBe(BuyerQuoteStatus::DRAFT);
    });

    it('belongs to a buyer', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($quote->buyer)->toBeInstanceOf(Company::class)
            ->and($quote->buyer->getKey())->toBe($this->buyer->getKey());
    });

    it('belongs to a request', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($quote->request)->toBeInstanceOf(Request::class)
            ->and($quote->request->getKey())->toBe($this->request->getKey());
    });

    it('belongs to a currency', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($quote->currency)->toBeInstanceOf(Currency::class)
            ->and($quote->currency->getKey())->toBe($this->currency->getKey());
    });

    it('has many items', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        BuyerQuoteItem::factory()->count(3)->forBuyerQuote($quote)->create();

        expect($quote->items)->toHaveCount(3);
    });
});

describe('BuyerQuote Status Transitions', function (): void {
    it('can edit draft quotes', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        expect($quote->can_edit)->toBeTrue();
    });

    it('can edit sent quotes', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        expect($quote->can_edit)->toBeTrue();
    });

    it('can create a new version from sent quotes', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        expect($quote->status->canCreateNewVersion())->toBeTrue();
    });

    it('cannot create a new version from draft quotes', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        expect($quote->status->canCreateNewVersion())->toBeFalse();
    });

    it('can send draft quotes', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        expect($quote->status->canSend())->toBeTrue();
    });

    it('marks quote as sent correctly', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        $quote->markAsSent();

        expect($quote->status)->toBe(BuyerQuoteStatus::SENT)
            ->and($quote->issued_at)->not->toBeNull();
    });

    it('advances request stage to awaiting buyer confirmation when quote is sent', function (): void {
        $this->request->update(['stage' => RequestStage::PREPARING_BUYER_QUOTE]);

        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        $quote->markAsSent();

        expect($this->request->fresh()->stage)->toBe(RequestStage::AWAITING_BUYER_CONFIRMATION);
    });

    it('marks quote as accepted correctly', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $quote->markAsAccepted();

        expect($quote->status)->toBe(BuyerQuoteStatus::ACCEPTED);
    });

    it('marks quote as rejected correctly', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $quote->markAsRejected();

        expect($quote->status)->toBe(BuyerQuoteStatus::REJECTED);
    });

    it('throws exception when sending non-draft quote', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $quote->markAsSent();
    })->throws(InvalidArgumentException::class);

    it('throws exception when accepting non-sent quote', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        $quote->markAsAccepted();
    })->throws(InvalidArgumentException::class);
});

describe('BuyerQuote Versioning', function (): void {
    it('creates new version from existing quote', function (): void {
        $originalQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        BuyerQuoteItem::factory()->forBuyerQuote($originalQuote)->create();

        $newQuote = $originalQuote->createNewVersion();

        expect($newQuote->version)->toBe(2)
            ->and($newQuote->previous_version_id)->toBe($originalQuote->getKey())
            ->and($newQuote->status)->toBe(BuyerQuoteStatus::DRAFT)
            ->and($newQuote->items)->toHaveCount(1);
    });

    it('marks original quote as superseded when creating new version', function (): void {
        $originalQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $originalQuote->createNewVersion();
        $originalQuote->refresh();

        expect($originalQuote->status)->toBe(BuyerQuoteStatus::SUPERSEDED);
    });

    it('can get previous version relationship', function (): void {
        $originalQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $newQuote = $originalQuote->createNewVersion();

        expect($newQuote->previousVersion)->toBeInstanceOf(BuyerQuote::class)
            ->and($newQuote->previousVersion->getKey())->toBe($originalQuote->getKey());
    });
});

describe('BuyerQuote Validity Extension', function (): void {
    it('extends validity date with reason', function (): void {
        $originalValidUntil = now()->addDays(10);
        $newValidUntil = now()->addDays(30);

        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->validUntil($originalValidUntil)
            ->sent()
            ->create();

        $extension = $quote->extendValidity($newValidUntil, 'Buyer requested more time');

        expect($extension)->toBeInstanceOf(BuyerQuoteExtension::class)
            ->and($extension->reason)->toBe('Buyer requested more time')
            ->and($quote->valid_until->format('Y-m-d'))->toBe($newValidUntil->format('Y-m-d'));
    });

    it('tracks extension history', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->validUntil(now()->addDays(10))
            ->sent()
            ->create();

        $quote->extendValidity(now()->addDays(20), 'First extension');
        $quote->extendValidity(now()->addDays(30), 'Second extension');

        expect($quote->extensions)->toHaveCount(2);
    });

    it('calculates extension days correctly', function (): void {
        $originalValidUntil = Carbon::parse('2026-01-15');
        $newValidUntil = Carbon::parse('2026-01-30');

        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->validUntil($originalValidUntil)
            ->sent()
            ->create();

        $extension = BuyerQuoteExtension::factory()
            ->forBuyerQuote($quote)
            ->extendedBy($this->user)
            ->withDates($originalValidUntil, $newValidUntil)
            ->create();

        expect($extension->extension_days)->toBe(15);
    });
});

describe('BuyerQuote Expiry', function (): void {
    it('detects expired quotes', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->expired()
            ->create();

        expect($quote->is_expired)->toBeTrue();
    });

    it('detects non-expired quotes', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->validUntil(now()->addDays(10))
            ->create();

        expect($quote->is_expired)->toBeFalse();
    });
});

describe('BuyerQuoteItem Model', function (): void {
    it('can create item with pricing', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->withPricing(costPrice: 100, unitPrice: 150, quantity: 2)
            ->create();

        expect($item->cost_price)->toBe('100.0000')
            ->and($item->unit_price)->toBe('150.0000')
            ->and($item->quantity)->toBe('2.0000');
    });

    it('belongs to buyer quote', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()->forBuyerQuote($quote)->create();

        expect($item->buyerQuote)->toBeInstanceOf(BuyerQuote::class)
            ->and($item->buyerQuote->getKey())->toBe($quote->getKey());
    });

    it('can link to request item', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();

        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->forRequestItem($requestItem)
            ->create();

        expect($item->requestItem)->toBeInstanceOf(RequestItem::class)
            ->and($item->requestItem->getKey())->toBe($requestItem->getKey());
    });

    it('can link to tax code', function (): void {
        $taxCode = TaxCode::factory()->recycle($this->team)->create(['rate' => 11]);

        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->withTaxCode($taxCode)
            ->create();

        expect($item->taxCode)->toBeInstanceOf(TaxCode::class)
            ->and($item->tax_rate)->toBe('11.0000');
    });
});

describe('BuyerQuoteItem Margin Calculation', function (): void {
    it('calculates margin amount correctly', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->withPricing(costPrice: 100, unitPrice: 130, quantity: 1)
            ->create();

        expect((float) $item->margin_amount)->toBe(30.0);
    });

    it('calculates margin percent correctly', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->withPricing(costPrice: 100, unitPrice: 125, quantity: 1)
            ->create();

        // margin_percent is stored on-selling (canonical): (125 - 100) / 125 * 100
        expect((float) $item->margin_percent)->toBe(20.0);
    });

    it('handles zero cost price for margin calculation', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->withPricing(costPrice: 0, unitPrice: 100, quantity: 1)
            ->create();

        // Zero cost on a 100 sell is a 100% on-selling margin (all revenue is margin).
        expect((float) $item->calculated_margin_percent)->toBe(100.0);
    });

    it('saves and reads back a large negative margin when cost far exceeds price', function (): void {
        // Regression: margin_percent was decimal(8,4), capped at +/-9999.9999.
        // A cost keyed far above the sell price (e.g. rupiah vs. thousands
        // mismatch) produces a margin percent well beyond that cap and must
        // be stored, not truncated or clamped, so the data-entry error stays
        // visible.
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->withPricing(costPrice: 600000, unitPrice: 100, quantity: 1)
            ->create();

        $item->refresh();

        expect((float) $item->margin_percent)->toBe(-599900.0);
    });
});

describe('BuyerQuoteItem Tax Calculation', function (): void {
    // On buyer items, unit_price is always the net price and is_tax_inclusive is the
    // "+ Tax" toggle: when off, no tax is added; when on, tax is added on top.
    it('adds no tax when the + Tax toggle is off', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->taxExclusive()
            ->create([
                'quantity' => '10.0000',
                'unit_price' => '100.0000',
                'cost_price' => '80.0000',
                'tax_rate' => '10.0000',
            ]);

        // Force recalculation
        $item->recalculatePrices();
        $item->save();

        expect((float) $item->line_subtotal)->toBe(1000.0)
            ->and((float) $item->line_tax)->toBe(0.0)
            ->and((float) $item->line_total)->toBe(1000.0);
    });

    it('adds tax on top of the net price when the + Tax toggle is on', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->taxInclusive()
            ->create([
                'quantity' => '10.0000',
                'unit_price' => '100.0000',
                'cost_price' => '80.0000',
                'tax_rate' => '10.0000',
            ]);

        // Force recalculation
        $item->recalculatePrices();
        $item->save();

        expect((float) $item->line_subtotal)->toBe(1000.0)
            ->and((float) $item->line_tax)->toBe(100.0)
            ->and((float) $item->line_total)->toBe(1100.0);
    });
});

describe('BuyerQuote Totals Recalculation', function (): void {
    it('recalculates totals from items', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        // qty=10, price=100, + Tax on at 10% → subtotal=1000, tax=100, total=1100
        BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->create([
                'quantity' => '10.0000',
                'unit_price' => '100.0000',
                'cost_price' => '80.0000',
                'tax_rate' => '10.0000',
                'is_tax_inclusive' => true,
            ]);

        // Manually recalculate totals
        $quote->recalculateTotals();
        $quote->refresh();

        expect((float) $quote->subtotal)->toBe(1000.0)
            ->and((float) $quote->tax_total)->toBe(100.0)
            ->and((float) $quote->total)->toBe(1100.0);
    });

    it('recalculates totals with multiple items', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        // Item 1: qty=5, price=100, + Tax on at 10% → subtotal=500, tax=50, total=550
        BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->create([
                'quantity' => '5.0000',
                'unit_price' => '100.0000',
                'cost_price' => '80.0000',
                'tax_rate' => '10.0000',
                'is_tax_inclusive' => true,
            ]);

        // Item 2: qty=3, price=100, + Tax on at 10% → subtotal=300, tax=30, total=330
        BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->create([
                'quantity' => '3.0000',
                'unit_price' => '100.0000',
                'cost_price' => '80.0000',
                'tax_rate' => '10.0000',
                'is_tax_inclusive' => true,
            ]);

        // Manually recalculate totals
        $quote->recalculateTotals();
        $quote->refresh();

        // Total: subtotal=800, tax=80, total=880
        expect((float) $quote->subtotal)->toBe(800.0)
            ->and((float) $quote->tax_total)->toBe(80.0)
            ->and((float) $quote->total)->toBe(880.0);
    });

    it('calculates total margin amount', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->create([
                'quantity' => '2.0000',
                'cost_price' => '100.0000',
                'unit_price' => '150.0000',
                'margin_amount' => '50.0000',
            ]);

        $quote->refresh();

        // margin_amount per unit * quantity = total margin (but accessor sums margin_amount column)
        expect($quote->total_margin_amount)->toBe(50.0);
    });
});

describe('BuyerQuote Number Generation', function (): void {
    it('generates quote numbers with expected format', function (): void {
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        // Quote number should match expected format. Sequence is a factory
        // fake (see BuyerQuoteFactory), not the real allocator, so it is not
        // constrained to 4 digits like the real format.
        expect($quote->quote_number)->toMatch('/^BQ-\d{4}-\d+$/');
    });

    it('generates quote number via static method', function (): void {
        $quoteNumber = BuyerQuote::generateNextNumber($this->team->getKey());

        expect($quoteNumber)->toMatch('/^BQ-\d{4}-\d{4}$/');
    });
});

describe('BuyerQuote Status Methods', function (): void {
    it('correctly identifies active status', function (): void {
        $draftQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        $sentQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $acceptedQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->accepted()
            ->create();

        expect($draftQuote->status->isActive())->toBeTrue()
            ->and($sentQuote->status->isActive())->toBeTrue()
            ->and($acceptedQuote->status->isActive())->toBeFalse();
    });
});

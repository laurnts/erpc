<?php

declare(strict_types=1);

use App\Models\Company;

use App\Enums\RequestType;
use App\Enums\SupplierQuoteStatus;
use App\Models\Article;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->actingAs($this->user);
});

describe('SupplierQuote Model', function (): void {
    it('can create a supplier quote with required fields', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($quote)->toBeInstanceOf(SupplierQuote::class)
            ->and($quote->status)->toBe(SupplierQuoteStatus::PENDING)
            ->and($quote->request_id)->toBe($this->request->getKey())
            ->and($quote->supplier_id)->toBe($this->supplier->getKey());
    });

    it('generates quote number on creation', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($quote->quote_number)->toMatch('/^SQ-\d{4}-\d{4}$/');
    });

    it('defaults to pending status', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($quote->status)->toBe(SupplierQuoteStatus::PENDING);
    });

    it('belongs to a request', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($quote->request)->toBeInstanceOf(Request::class)
            ->and($quote->request->getKey())->toBe($this->request->getKey());
    });

    it('belongs to a supplier', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($quote->supplier)->toBeInstanceOf(Company::class)
            ->and($quote->supplier->getKey())->toBe($this->supplier->getKey());
    });

    it('belongs to a currency', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($quote->currency)->toBeInstanceOf(Currency::class)
            ->and($quote->currency->getKey())->toBe($this->currency->getKey());
    });

    it('has many items', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        SupplierQuoteItem::factory()->count(3)->recycle($quote)->create();

        expect($quote->items)->toHaveCount(3);
    });

    it('tracks exchange rate', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'exchange_rate' => '15000.00000000',
            ]);

        expect((float) $quote->exchange_rate)->toBe(15000.0);
    });
});

describe('SupplierQuote Status', function (): void {
    it('can mark quote as selected', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $quote->markAsSelected();

        expect($quote->status)->toBe(SupplierQuoteStatus::SELECTED);
    });

    it('can mark quote as rejected', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $quote->markAsRejected();

        expect($quote->status)->toBe(SupplierQuoteStatus::REJECTED);
    });

    it('detects expired quotes', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'valid_until' => now()->subDays(10),
            ]);

        expect($quote->is_expired)->toBeTrue()
            ->and($quote->is_valid)->toBeFalse();
    });

    it('detects valid quotes', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'valid_until' => now()->addDays(30),
            ]);

        expect($quote->is_expired)->toBeFalse()
            ->and($quote->is_valid)->toBeTrue();
    });
});

describe('SupplierQuote Totals', function (): void {
    it('recalculates totals from items', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'exchange_rate' => '1.00000000',
            ]);

        // Create items with known values (tax exclusive)
        SupplierQuoteItem::factory()->recycle($quote)->create([
            'quantity' => '10.0000',
            'unit_price' => '100.0000',
            'tax_rate' => '10.0000',
            'is_tax_inclusive' => false,
            'line_subtotal' => '1000.0000',
            'line_tax' => '100.0000',
            'line_total' => '1100.0000',
        ]);

        SupplierQuoteItem::factory()->recycle($quote)->create([
            'quantity' => '5.0000',
            'unit_price' => '200.0000',
            'tax_rate' => '10.0000',
            'is_tax_inclusive' => false,
            'line_subtotal' => '1000.0000',
            'line_tax' => '100.0000',
            'line_total' => '1100.0000',
        ]);

        $quote->recalculateTotals();

        expect((float) $quote->subtotal)->toBe(2000.0)
            ->and((float) $quote->tax_total)->toBe(200.0)
            ->and((float) $quote->total)->toBe(2200.0);
    });

    it('recalculates service request totals from main items only', function (): void {
        $serviceRequest = Request::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->create(['request_type' => RequestType::SERVICE]);

        $mainItem = RequestItem::factory()->recycle($serviceRequest)->create([
            'parent_id' => null,
            'quantity' => '1.0000',
        ]);
        $childItem = RequestItem::factory()->recycle($serviceRequest)->create([
            'parent_id' => $mainItem->getKey(),
            'quantity' => '1.0000',
        ]);

        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($serviceRequest)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create(['exchange_rate' => '1.00000000']);

        // Seed the calculation inputs (unit_price/quantity/tax_rate); the item
        // observer derives line_subtotal/line_tax/line_total on save, so seeding
        // those directly would be overwritten. Supplier is taxable, 11% added.
        SupplierQuoteItem::factory()->recycle($quote)->create([
            'request_item_id' => $mainItem->getKey(),
            'quantity' => '1.0000',
            'unit_price' => '5000.0000',
            'tax_rate' => '11.0000',
            'is_tax_inclusive' => false,
        ]);

        SupplierQuoteItem::factory()->recycle($quote)->create([
            'request_item_id' => $childItem->getKey(),
            'quantity' => '1.0000',
            'unit_price' => '3500.0000',
            'tax_rate' => '11.0000',
            'is_tax_inclusive' => false,
        ]);

        SupplierQuoteItem::factory()->recycle($quote)->create([
            'request_item_id' => null,
            'quantity' => '1.0000',
            'unit_price' => '30000.0000',
            'tax_rate' => '11.0000',
            'is_tax_inclusive' => false,
        ]);

        $quote->recalculateTotals();

        expect((float) $quote->subtotal)->toBe(5000.0)
            ->and((float) $quote->tax_total)->toBe(550.0)
            ->and((float) $quote->total)->toBe(5550.0);
    });

    it('calculates base currency values with exchange rate', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'exchange_rate' => '15000.00000000',
            ]);

        SupplierQuoteItem::factory()->recycle($quote)->create([
            'quantity' => '1.0000',
            'unit_price' => '100.0000',
            'tax_rate' => '0.0000',
            'is_tax_inclusive' => false,
            'line_subtotal' => '100.0000',
            'line_tax' => '0.0000',
            'line_total' => '100.0000',
        ]);

        $quote->recalculateTotals();

        expect((float) $quote->subtotal)->toBe(100.0)
            ->and((float) $quote->total)->toBe(100.0)
            ->and((float) $quote->subtotal_base)->toBe(1500000.0)
            ->and((float) $quote->total_base)->toBe(1500000.0);
    });

    it('returns cost summary', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'subtotal' => '1000.0000',
                'tax_total' => '100.0000',
                'total' => '1100.0000',
                'subtotal_base' => '1000.0000',
                'tax_total_base' => '100.0000',
                'total_base' => '1100.0000',
                'exchange_rate' => '1.00000000',
            ]);

        $summary = $quote->getCostSummary();

        expect($summary['subtotal'])->toBe(1000.0)
            ->and($summary['tax_total'])->toBe(100.0)
            ->and($summary['total'])->toBe(1100.0)
            ->and($summary['currency_code'])->toBe('USD')
            ->and($summary['exchange_rate'])->toBe(1.0);
    });
});

describe('SupplierQuoteItem Model', function (): void {
    it('can create a quote item with required fields', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierQuoteItem::factory()->recycle($quote)->create([
            'description' => 'Test Item',
            'quantity' => '10.0000',
            'unit_price' => '50.0000',
        ]);

        expect($item)->toBeInstanceOf(SupplierQuoteItem::class)
            ->and($item->description)->toBe('Test Item');
    });

    it('belongs to a supplier quote', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierQuoteItem::factory()->recycle($quote)->create();

        expect($item->supplierQuote)->toBeInstanceOf(SupplierQuote::class)
            ->and($item->supplierQuote->getKey())->toBe($quote->getKey());
    });

    it('can link to request item for traceability', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();

        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierQuoteItem::factory()->recycle($quote)->create([
            'request_item_id' => $requestItem->getKey(),
        ]);

        expect($item->requestItem)->toBeInstanceOf(RequestItem::class)
            ->and($item->requestItem->getKey())->toBe($requestItem->getKey());
    });

    it('can link to article', function (): void {
        $article = Article::factory()->recycle($this->team)->create();

        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierQuoteItem::factory()->recycle($quote)->create([
            'article_id' => $article->getKey(),
        ]);

        expect($item->article)->toBeInstanceOf(Article::class)
            ->and($item->article->getKey())->toBe($article->getKey());
    });
});

describe('SupplierQuoteItem Tax Calculations', function (): void {
    it('calculates tax exclusive totals correctly', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        // Create item and manually test calculation method
        $item = new SupplierQuoteItem;
        $item->supplier_quote_id = $quote->getKey();
        $item->description = 'Test Item';
        $item->quantity = '10.0000';
        $item->unit = 'pcs';
        $item->unit_price = '100.0000';
        $item->tax_rate = '10.0000';
        $item->is_tax_inclusive = false;

        // Manually call calculateTotals to test the method
        $item->calculateTotals();

        // Verify calculation results
        expect((float) $item->line_subtotal)->toBe(1000.0)
            ->and((float) $item->line_tax)->toBe(100.0)
            ->and((float) $item->line_total)->toBe(1100.0);
    });

    it('calculates tax inclusive totals correctly', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        // Create item and manually test calculation method
        $item = new SupplierQuoteItem;
        $item->supplier_quote_id = $quote->getKey();
        $item->description = 'Test Item';
        $item->quantity = '1.0000';
        $item->unit = 'pcs';
        $item->unit_price = '110.0000';
        $item->tax_rate = '10.0000';
        $item->is_tax_inclusive = true;

        // Manually call calculateTotals to test the method
        $item->calculateTotals();

        // 110 inclusive with 10% tax = 100 subtotal + 10 tax
        expect((float) $item->line_total)->toBe(110.0)
            ->and((float) $item->line_subtotal)->toBe(100.0)
            ->and((float) $item->line_tax)->toBe(10.0);
    });

    it('stores unit price excluding tax', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        // Create item and manually test calculation method
        $item = new SupplierQuoteItem;
        $item->supplier_quote_id = $quote->getKey();
        $item->description = 'Test Item';
        $item->quantity = '1.0000';
        $item->unit = 'pcs';
        $item->unit_price = '110.0000';
        $item->tax_rate = '10.0000';
        $item->is_tax_inclusive = true;

        // Manually call calculateTotals to test the method
        $item->calculateTotals();

        // 110 inclusive = 100 exc tax
        expect((float) $item->unit_price_exc_tax)->toBe(100.0);
    });

    it('has observer attribute registered', function (): void {
        // Verify the observer is registered via attribute
        $reflectionClass = new ReflectionClass(SupplierQuoteItem::class);
        $attributes = $reflectionClass->getAttributes(Illuminate\Database\Eloquent\Attributes\ObservedBy::class);

        expect($attributes)->toHaveCount(1);

        $observerClass = $attributes[0]->getArguments()[0];
        expect($observerClass)->toBe(App\Observers\SupplierQuoteItemObserver::class);
    });
});

describe('Request SupplierQuotes Relationship', function (): void {
    it('request has many supplier quotes', function (): void {
        SupplierQuote::factory()
            ->count(3)
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($this->request->supplierQuotes)->toHaveCount(3);
    });
});

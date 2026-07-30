<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
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

describe('SupplierOrder Model', function (): void {
    it('can create a supplier order with required fields', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order)->toBeInstanceOf(SupplierOrder::class)
            ->and($order->status)->toBe(OrderStatus::DRAFT)
            ->and($order->request_id)->toBe($this->request->getKey())
            ->and($order->supplier_id)->toBe($this->supplier->getKey());
    });

    it('generates PO number on creation', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order->po_number)->toMatch('/^PO-\d{4}-\d{4}$/');
    });

    it('defaults to draft status', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order->status)->toBe(OrderStatus::DRAFT);
    });

    it('belongs to a request', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order->request)->toBeInstanceOf(Request::class)
            ->and($order->request->getKey())->toBe($this->request->getKey());
    });

    it('belongs to a supplier', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order->supplier)->toBeInstanceOf(Company::class)
            ->and($order->supplier->getKey())->toBe($this->supplier->getKey());
    });

    it('belongs to a currency', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order->currency)->toBeInstanceOf(Currency::class)
            ->and($order->currency->getKey())->toBe($this->currency->getKey());
    });

    it('has many items', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        SupplierOrderItem::factory()->count(3)->recycle($order)->create();

        expect($order->items)->toHaveCount(3);
    });

    it('tracks exchange rate', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'exchange_rate' => '15000.00000000',
            ]);

        expect((float) $order->exchange_rate)->toBe(15000.0);
    });

    it('can have an optional supplier quote reference', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'supplier_quote_id' => $quote->getKey(),
            ]);

        expect($order->supplierQuote)->toBeInstanceOf(SupplierQuote::class)
            ->and($order->supplierQuote->getKey())->toBe($quote->getKey());
    });
});

describe('SupplierOrder PO Number Generation', function (): void {
    it('generates PO number with suffix for multiple orders on same request', function (): void {
        // Create first order
        $order1 = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        // Create second order for same request
        $order2 = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order1->po_number)->toMatch('/^PO-\d{4}-\d{4}$/')
            ->and($order2->po_number)->toMatch('/^PO-\d{4}-\d{4}-[A-Z]$/');
    });

    it('shares the base number from a previous year when splitting a PO across a year boundary', function (): void {
        // The first order's PO number carries last year's year segment. The
        // suffix branch must derive the base year from this number, not from
        // today's date, or the second order silently gets an unrelated fresh
        // base number instead of joining its sibling.
        $previousYear = (int) date('Y') - 1;

        $order1 = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create(['po_number' => "PO-{$previousYear}-0042"]);

        $order2 = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order2->po_number)->toBe("PO-{$previousYear}-0042-B");
    });
});

describe('SupplierOrder Status', function (): void {
    it('can confirm a draft order', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $order->confirm();

        expect($order->status)->toBe(OrderStatus::CONFIRMED)
            ->and($order->confirmed_at)->not->toBeNull();
    });

    it('can cancel a draft order', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $order->cancel();

        expect($order->status)->toBe(OrderStatus::CANCELLED);
    });

    it('can mark order as sent', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $order->markAsOrdered();

        expect($order->ordered_at)->not->toBeNull()
            ->and($order->status)->toBe(OrderStatus::CONFIRMED);
    });

    it('is editable when in draft status', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order->is_editable)->toBeTrue();
    });

    it('is not editable when confirmed', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->confirmed()
            ->create();

        expect($order->is_editable)->toBeFalse();
    });

    it('is cancellable when draft', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($order->is_cancellable)->toBeTrue();
    });

    it('is cancellable when confirmed', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->confirmed()
            ->create();

        expect($order->is_cancellable)->toBeTrue();
    });

    it('is not cancellable when processing', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->processing()
            ->create();

        expect($order->is_cancellable)->toBeFalse();
    });
});

describe('SupplierOrder Totals', function (): void {
    it('recalculates totals from items', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'exchange_rate' => '1.00000000',
            ]);

        // Create items with known values
        SupplierOrderItem::factory()->recycle($order)->create([
            'quantity' => '10.0000',
            'unit_price' => '100.0000',
            'unit_price_exc_tax' => '100.0000',
            'tax_rate' => '10.0000',
            'tax_amount' => '10.0000',
            'is_tax_inclusive' => false,
            'line_total' => '1100.0000',
        ]);

        SupplierOrderItem::factory()->recycle($order)->create([
            'quantity' => '5.0000',
            'unit_price' => '200.0000',
            'unit_price_exc_tax' => '200.0000',
            'tax_rate' => '10.0000',
            'tax_amount' => '20.0000',
            'is_tax_inclusive' => false,
            'line_total' => '1100.0000',
        ]);

        $order->recalculateTotals();

        expect((float) $order->subtotal)->toBe(2000.0)
            ->and((float) $order->tax_total)->toBe(200.0)
            ->and((float) $order->total)->toBe(2200.0);
    });

    it('calculates base currency values with exchange rate', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'exchange_rate' => '15000.00000000',
            ]);

        SupplierOrderItem::factory()->recycle($order)->create([
            'quantity' => '1.0000',
            'unit_price' => '100.0000',
            'unit_price_exc_tax' => '100.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'is_tax_inclusive' => false,
            'line_total' => '100.0000',
        ]);

        $order->recalculateTotals();

        expect((float) $order->subtotal)->toBe(100.0)
            ->and((float) $order->total)->toBe(100.0)
            ->and((float) $order->base_subtotal)->toBe(1500000.0)
            ->and((float) $order->base_total)->toBe(1500000.0);
    });

    it('returns cost summary', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'subtotal' => '1000.0000',
                'tax_total' => '100.0000',
                'total' => '1100.0000',
                'base_subtotal' => '1000.0000',
                'base_tax_total' => '100.0000',
                'base_total' => '1100.0000',
                'exchange_rate' => '1.00000000',
            ]);

        $summary = $order->getCostSummary();

        expect($summary['subtotal'])->toBe(1000.0)
            ->and($summary['tax_total'])->toBe(100.0)
            ->and($summary['total'])->toBe(1100.0)
            ->and($summary['currency_code'])->toBe('USD')
            ->and($summary['exchange_rate'])->toBe(1.0);
    });
});

describe('SupplierOrder Create From Quote', function (): void {
    it('can create order from supplier quote', function (): void {
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
                'notes' => 'Quote notes',
            ]);

        SupplierQuoteItem::factory()->recycle($quote)->create([
            'description' => 'Test Item',
            'quantity' => '10.0000',
            'unit' => 'pcs',
            'unit_price' => '100.0000',
            'unit_price_exc_tax' => '100.0000',
            'tax_rate' => '10.0000',
            'tax_amount' => '10.0000',
            'is_tax_inclusive' => false,
            'line_total' => '1100.0000',
        ]);

        $order = SupplierOrder::createFromQuote($quote);

        expect($order)->toBeInstanceOf(SupplierOrder::class)
            ->and($order->supplier_quote_id)->toBe($quote->getKey())
            ->and($order->supplier_id)->toBe($quote->supplier_id)
            ->and($order->currency_id)->toBe($quote->currency_id)
            ->and((float) $order->total)->toBe(1100.0)
            ->and($order->notes)->toBe('Quote notes')
            ->and($order->items)->toHaveCount(1);
    });

    it('copies item tax fields from quote', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        SupplierQuoteItem::factory()->recycle($quote)->create([
            'description' => 'Test Item',
            'quantity' => '10.0000',
            'unit' => 'pcs',
            'unit_price' => '110.0000',
            'unit_price_exc_tax' => '100.0000',
            'tax_rate' => '10.0000',
            'tax_amount' => '10.0000',
            'is_tax_inclusive' => true,
            'line_total' => '1100.0000',
        ]);

        $order = SupplierOrder::createFromQuote($quote);
        $orderItem = $order->items->first();

        expect($orderItem->is_tax_inclusive)->toBeTrue()
            ->and((float) $orderItem->tax_rate)->toBe(10.0)
            ->and((float) $orderItem->unit_price_exc_tax)->toBe(100.0);
    });
});

describe('SupplierOrderItem Model', function (): void {
    it('can create an order item with required fields', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierOrderItem::factory()->recycle($order)->create([
            'description' => 'Test Item',
            'quantity' => '10.0000',
            'unit_price' => '50.0000',
        ]);

        expect($item)->toBeInstanceOf(SupplierOrderItem::class)
            ->and($item->description)->toBe('Test Item');
    });

    it('belongs to a supplier order', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierOrderItem::factory()->recycle($order)->create();

        expect($item->supplierOrder)->toBeInstanceOf(SupplierOrder::class)
            ->and($item->supplierOrder->getKey())->toBe($order->getKey());
    });

    it('can link to request item for traceability', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();

        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierOrderItem::factory()->recycle($order)->create([
            'request_item_id' => $requestItem->getKey(),
        ]);

        expect($item->requestItem)->toBeInstanceOf(RequestItem::class)
            ->and($item->requestItem->getKey())->toBe($requestItem->getKey());
    });

    it('can link to article', function (): void {
        $article = Article::factory()->recycle($this->team)->create();

        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierOrderItem::factory()->recycle($order)->create([
            'article_id' => $article->getKey(),
        ]);

        expect($item->article)->toBeInstanceOf(Article::class)
            ->and($item->article->getKey())->toBe($article->getKey());
    });

    it('can link to supplier quote item', function (): void {
        $quote = SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $quoteItem = SupplierQuoteItem::factory()->recycle($quote)->create();

        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create(['supplier_quote_id' => $quote->getKey()]);

        $item = SupplierOrderItem::factory()->recycle($order)->create([
            'supplier_quote_item_id' => $quoteItem->getKey(),
        ]);

        expect($item->supplierQuoteItem)->toBeInstanceOf(SupplierQuoteItem::class)
            ->and($item->supplierQuoteItem->getKey())->toBe($quoteItem->getKey());
    });
});

describe('SupplierOrderItem Tax Calculations', function (): void {
    it('calculates tax exclusive line totals correctly', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = new SupplierOrderItem;
        $item->supplier_order_id = $order->getKey();
        $item->description = 'Test Item';
        $item->quantity = '10.0000';
        $item->unit = 'pcs';
        $item->unit_price = '100.0000';
        $item->tax_rate = '10.0000';
        $item->is_tax_inclusive = false;

        $item->calculateLineTotal();

        expect((float) $item->line_total)->toBe(1100.0)
            ->and((float) $item->unit_price_exc_tax)->toBe(100.0)
            ->and((float) $item->tax_amount)->toBe(10.0);
    });

    it('calculates tax inclusive line totals correctly', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = new SupplierOrderItem;
        $item->supplier_order_id = $order->getKey();
        $item->description = 'Test Item';
        $item->quantity = '1.0000';
        $item->unit = 'pcs';
        $item->unit_price = '110.0000';
        $item->tax_rate = '10.0000';
        $item->is_tax_inclusive = true;

        $item->calculateLineTotal();

        // 110 inclusive with 10% tax = 100 exc tax
        expect((float) $item->line_total)->toBe(110.0)
            ->and((float) $item->unit_price_exc_tax)->toBe(100.0)
            ->and((float) $item->tax_amount)->toBe(10.0);
    });
});

describe('Hierarchical display lines', function (): void {
    it('shows child buyer quote items under main supplier order lines without affecting totals', function (): void {
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $mainReqItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => null,
            'supplier_id' => $supplier->getKey(),
        ]);
        $childReqItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $mainReqItem->getKey(),
            'supplier_id' => $supplier->getKey(),
        ]);

        $quote = \App\Models\BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->accepted()
            ->create();

        \App\Models\BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
            'request_item_id' => $mainReqItem->getKey(),
            'description' => 'Main service',
            'quantity' => '1',
            'cost_price' => '600000',
            'sort_order' => 1,
        ]);
        \App\Models\BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
            'request_item_id' => $childReqItem->getKey(),
            'description' => 'Child detail work',
            'quantity' => '1',
            'cost_price' => '50000',
            'sort_order' => 2,
        ]);

        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($supplier)
            ->recycle($this->currency)
            ->create();

        SupplierOrderItem::factory()->forSupplierOrder($order)->create([
            'request_item_id' => $mainReqItem->getKey(),
            'description' => 'Main service',
            'quantity' => '1',
            'unit_price' => '600000',
            'unit_price_exc_tax' => '600000',
            'tax_rate' => '0',
            'tax_amount' => '0',
            'line_total' => '600000',
            'sort_order' => 1,
        ]);

        $order->recalculateTotals();
        $order->refresh();

        $lines = $order->hierarchicalDisplayLines();

        expect($lines)->toHaveCount(2)
            ->and($lines[0]['is_child'])->toBeFalse()
            ->and($lines[0]['label'])->toContain('Main service')
            ->and($lines[1]['is_child'])->toBeTrue()
            ->and($lines[1]['label'])->toContain('Child detail work')
            ->and((float) $order->total)->toBe(600000.0);
    });

    it('orders supplier order PDF items hierarchically under their parent', function (): void {
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $mainReqItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => null,
            'supplier_id' => $supplier->getKey(),
        ]);
        $childReqItem = RequestItem::factory()->recycle($this->request)->create([
            'parent_id' => $mainReqItem->getKey(),
            'supplier_id' => $supplier->getKey(),
        ]);

        $quote = \App\Models\BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->accepted()
            ->create();

        \App\Models\BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
            'request_item_id' => $mainReqItem->getKey(),
            'description' => 'Main work',
            'sort_order' => 1,
        ]);
        \App\Models\BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
            'request_item_id' => $childReqItem->getKey(),
            'description' => 'Child work',
            'sort_order' => 2,
        ]);

        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($supplier)
            ->recycle($this->currency)
            ->create(['po_number' => 'PO-2026-0099']);

        SupplierOrderItem::factory()->forSupplierOrder($order)->create([
            'request_item_id' => $mainReqItem->getKey(),
            'description' => 'Main work',
            'sort_order' => 1,
        ]);

        $html = view('pdf.supplier-order', [
            'order' => $order->load(['supplier', 'currency', 'items']),
            'company' => ['name' => 'Test Co', 'address' => '', 'phone' => '', 'email' => ''],
        ])->render();

        $mainPos = strpos($html, 'Main work');
        $childPos = strpos($html, 'Child work');

        expect($mainPos)->not->toBeFalse()
            ->and($childPos)->not->toBeFalse()
            ->and($mainPos)->toBeLessThan($childPos)
            ->and($html)->toContain('↳');
    });
});

describe('Request SupplierOrders Relationship', function (): void {
    it('request has many supplier orders', function (): void {
        SupplierOrder::factory()
            ->count(3)
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($this->request->supplierOrders)->toHaveCount(3);
    });
});

describe('Observer Registration', function (): void {
    it('has observer attribute registered', function (): void {
        $reflectionClass = new ReflectionClass(SupplierOrder::class);
        $attributes = $reflectionClass->getAttributes(Illuminate\Database\Eloquent\Attributes\ObservedBy::class);

        expect($attributes)->toHaveCount(1);

        $observerClass = $attributes[0]->getArguments()[0];
        expect($observerClass)->toBe(App\Observers\SupplierOrderObserver::class);
    });
});

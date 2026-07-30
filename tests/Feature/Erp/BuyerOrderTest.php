<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_limit' => 10000,
    ]);
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->actingAs($this->user);
});

describe('BuyerOrder Model', function (): void {
    it('can create a buyer order with required fields', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create([
                'status' => OrderStatus::DRAFT,
            ]);

        expect($order)->toBeInstanceOf(BuyerOrder::class)
            ->and($order->status)->toBe(OrderStatus::DRAFT);
    });

    it('generates order number on creation', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        // Sequence is a factory fake (see BuyerOrderFactory), not the real
        // allocator, so it is not constrained to 4 digits like the real format.
        expect($order->order_number)->toMatch('/^BO-\d{4}-\d+$/');
    });

    it('defaults to draft status', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        expect($order->status)->toBe(OrderStatus::DRAFT);
    });

    it('belongs to a buyer', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        expect($order->buyer)->toBeInstanceOf(Company::class)
            ->and($order->buyer->getKey())->toBe($this->buyer->getKey());
    });

    it('belongs to a request', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        expect($order->request)->toBeInstanceOf(Request::class)
            ->and($order->request->getKey())->toBe($this->request->getKey());
    });

    it('has many items', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        BuyerOrderItem::factory()->count(3)->forBuyerOrder($order)->create();

        expect($order->items)->toHaveCount(3);
    });
});

describe('BuyerOrder Status Transitions', function (): void {
    it('can edit draft orders', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->draft()
            ->create();

        expect($order->can_edit)->toBeTrue();
    });

    it('cannot edit confirmed orders', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->confirmed()
            ->create();

        expect($order->can_edit)->toBeFalse();
    });

    it('can confirm draft orders', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->draft()
            ->create();

        expect($order->status->canConfirm())->toBeTrue();
    });

    it('confirms order correctly', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->draft()
            ->create();

        $order->confirm();

        expect($order->status)->toBe(OrderStatus::CONFIRMED)
            ->and($order->confirmed_at)->not->toBeNull();
    });

    it('cancels order correctly', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->draft()
            ->create();

        $order->cancel();

        expect($order->status)->toBe(OrderStatus::CANCELLED);
    });

    it('throws exception when confirming non-draft order', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->confirmed()
            ->create();

        $order->confirm();
    })->throws(InvalidArgumentException::class);

    it('throws exception when cancelling completed order', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->completed()
            ->create();

        $order->cancel();
    })->throws(InvalidArgumentException::class);

    it('can progress through status workflow', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->confirmed()
            ->create();

        $order->progressStatus();
        expect($order->status)->toBe(OrderStatus::APPROVED);

        $order->progressStatus();
        expect($order->status)->toBe(OrderStatus::PROCESSING);

        $order->progressStatus();
        expect($order->status)->toBe(OrderStatus::SHIPPED);
    });
});

describe('BuyerOrder Create From Quote', function (): void {
    it('creates order from accepted quote', function (): void {
        $buyerQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->accepted()
            ->withTotals(1000, 100, 1100)
            ->create([
                'payment_terms_days' => 45,
                'payment_terms_description' => 'Net 45',
                'prepayment_type' => \App\Enums\PrepaymentType::PERCENT,
                'prepayment_percent' => 0,
                'prepayment_amount' => '0.0000',
            ]);

        BuyerQuoteItem::factory()
            ->forBuyerQuote($buyerQuote)
            ->create([
                'description' => 'Test Item',
                'quantity' => '10.0000',
                'unit_price' => '100.0000',
                'unit_price_exc_tax' => '100.0000',
                'tax_rate' => '10.0000',
                'line_total' => '1100.0000',
            ]);

        $order = BuyerOrder::createFromQuote($buyerQuote);

        expect($order)->toBeInstanceOf(BuyerOrder::class)
            ->and($order->buyer_quote_id)->toBe($buyerQuote->getKey())
            ->and($order->status)->toBe(OrderStatus::DRAFT)
            ->and($order->payment_terms_days)->toBe(45)
            ->and($order->payment_terms_text)->toBe('Net 45')
            ->and((float) $order->total)->toBe(1100.0)
            ->and($order->items)->toHaveCount(1);
    });

    it('throws exception when creating order from non-accepted quote', function (): void {
        $buyerQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        BuyerOrder::createFromQuote($buyerQuote);
    })->throws(InvalidArgumentException::class);

    it('copies items with locked tax fields from quote', function (): void {
        $taxCode = TaxCode::factory()->recycle($this->team)->create(['rate' => 11]);

        $buyerQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->accepted()
            ->create();

        BuyerQuoteItem::factory()
            ->forBuyerQuote($buyerQuote)
            ->withTaxCode($taxCode)
            ->taxInclusive()
            ->create([
                'description' => 'Item with tax',
                'tax_rate' => '11.0000',
            ]);

        $order = BuyerOrder::createFromQuote($buyerQuote);
        $orderItem = $order->items->first();

        expect($orderItem->tax_code_id)->toBe($taxCode->getKey())
            ->and($orderItem->is_tax_inclusive)->toBeTrue()
            ->and($orderItem->tax_rate)->toBe('11.0000');
    });

    it('locks payment terms from quote', function (): void {
        $buyerQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->accepted()
            ->create([
                'payment_terms_days' => 60,
                'payment_terms_description' => 'Custom terms - Net 60',
                'prepayment_type' => \App\Enums\PrepaymentType::PERCENT,
                'prepayment_percent' => 0,
                'prepayment_amount' => '0.0000',
            ]);

        $order = BuyerOrder::createFromQuote($buyerQuote);

        expect($order->payment_terms_days)->toBe(60)
            ->and($order->payment_terms_text)->toBe('Custom terms - Net 60');
    });

    it('locks prepayment and payment schedule from quote', function (): void {
        $buyerQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->accepted()
            ->create([
                'prepayment_type' => \App\Enums\PrepaymentType::PERCENT,
                'prepayment_percent' => 0,
                'prepayment_amount' => '50.0000',
            ]);

        \App\Models\BuyerQuotePaymentTerm::factory()->for($buyerQuote)->create([
            'due_days' => 30,
            'percentage' => 50,
            'sort_order' => 0,
        ]);
        \App\Models\BuyerQuotePaymentTerm::factory()->for($buyerQuote)->create([
            'due_days' => 7,
            'percentage' => 50,
            'sort_order' => 1,
        ]);

        $order = BuyerOrder::createFromQuote($buyerQuote->fresh('paymentTerms'));

        expect($order->payment_terms_days)->toBe(30)
            ->and($order->payment_terms_text)->toBe("1. Prepayment: 50%\n2. Payment term 1: 30 days - 50%\n3. Payment term 2: 7 days - 50%")
            ->and($order->payment_terms_lines)->toBe([
                '1. Prepayment: 50%',
                '2. Payment term 1: 30 days - 50%',
                '3. Payment term 2: 7 days - 50%',
            ]);
    });

    it('rebuilds payment terms display from source quote for legacy orders', function (): void {
        $buyerQuote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->accepted()
            ->create([
                'prepayment_type' => \App\Enums\PrepaymentType::PERCENT,
                'prepayment_percent' => 0,
                'prepayment_amount' => '50.0000',
            ]);

        \App\Models\BuyerQuotePaymentTerm::factory()->for($buyerQuote)->create([
            'due_days' => 30,
            'percentage' => 50,
            'sort_order' => 0,
        ]);
        \App\Models\BuyerQuotePaymentTerm::factory()->for($buyerQuote)->create([
            'due_days' => 7,
            'percentage' => 50,
            'sort_order' => 1,
        ]);

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create([
                'buyer_quote_id' => $buyerQuote->getKey(),
                'payment_terms_text' => '50% in 30 days, 50% in 7 days',
            ]);

        expect($order->payment_terms_display)->toBe("1. Prepayment: 50%\n2. Payment term 1: 30 days - 50%\n3. Payment term 2: 7 days - 50%");
    });
});

describe('BuyerOrder Credit Limit Check', function (): void {
    it('detects when order exceeds credit limit', function (): void {
        $buyer = Company::factory()->buyer()->recycle($this->team)->create([
            'credit_limit' => 1000,
        ]);

        // exceeds_credit_limit now reads derived exposure, so a seeded
        // credit_used column no longer means anything — confirm a real order
        // to establish 500 of genuine exposure instead.
        BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(500, 0, 500)
            ->create(['status' => OrderStatus::DRAFT])
            ->confirm();

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(600, 0, 600)
            ->create();

        // Available credit: 1000 - 500 = 500
        // Order total: 600
        // 600 > 500, so exceeds
        expect($order->exceeds_credit_limit)->toBeTrue();
    });

    it('does not flag order within credit limit', function (): void {
        $buyer = Company::factory()->buyer()->recycle($this->team)->create([
            'credit_limit' => 10000,
        ]);

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(500, 50, 550)
            ->create();

        expect($order->exceeds_credit_limit)->toBeFalse();
    });

    it('returns warning message when credit limit exceeded', function (): void {
        $buyer = Company::factory()->buyer()->recycle($this->team)->create([
            'credit_limit' => 1000,
        ]);

        // getCreditLimitWarning() now reads derived exposure, so a seeded
        // credit_used column no longer means anything — confirm a real order
        // to establish 800 of genuine exposure instead.
        BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(800, 0, 800)
            ->create(['status' => OrderStatus::DRAFT])
            ->confirm();

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(300, 0, 300)
            ->create();

        $warning = $order->getCreditLimitWarning();

        expect($warning)->not->toBeNull()
            ->and($warning)->toContain('exceeds available credit');
    });

    it('returns null when no credit limit set', function (): void {
        $buyer = Company::factory()->buyer()->recycle($this->team)->create([
            'credit_limit' => 0,
        ]);

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(1000000, 0, 1000000)
            ->create();

        expect($order->getCreditLimitWarning())->toBeNull();
    });
});

describe('BuyerOrder Credit Restoration', function (): void {
    it('restores credit exactly once when a confirmed order is cancelled', function (): void {
        $buyer = Company::factory()->buyer()->recycle($this->team)->create([
            'credit_status' => true,
            'credit_limit' => 10000,
        ]);

        // credit_exposure is now derived from confirmed orders, so the 1000
        // baseline "already used" must come from a real confirmed order
        // instead of a seeded credit_used column.
        BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(1000, 0, 1000)
            ->create(['status' => OrderStatus::DRAFT])
            ->confirm();

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(2000, 0, 2000)
            ->create(['status' => OrderStatus::DRAFT]);

        $order->confirm();
        expect($buyer->fresh()->credit_exposure)->toBe(3000.0)
            ->and($buyer->fresh()->derived_available_credit)->toBe(7000.0);

        $order->cancel();

        // Credit restored once (cancel() handles it); the observer must not double-restore.
        expect($buyer->fresh()->credit_exposure)->toBe(1000.0)
            ->and($buyer->fresh()->derived_available_credit)->toBe(9000.0);
    });

    it('restores credit once on a direct status change away from confirmed', function (): void {
        $buyer = Company::factory()->buyer()->recycle($this->team)->create([
            'credit_status' => true,
            'credit_limit' => 10000,
        ]);

        BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(1000, 0, 1000)
            ->create(['status' => OrderStatus::DRAFT])
            ->confirm();

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->forBuyer($buyer)
            ->forRequest($this->request)
            ->withTotals(2000, 0, 2000)
            ->create(['status' => OrderStatus::DRAFT]);

        $order->confirm();

        // Direct status change (no cancel()/progressStatus()) — the observer restores.
        $order->update(['status' => OrderStatus::SHIPPED]);

        expect($buyer->fresh()->credit_exposure)->toBe(1000.0)
            ->and($buyer->fresh()->derived_available_credit)->toBe(9000.0);
    });
});

describe('BuyerOrderItem Model', function (): void {
    it('can create item with pricing', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        $item = BuyerOrderItem::factory()
            ->forBuyerOrder($order)
            ->withPricing(unitPrice: 100, quantity: 2, taxRate: 10)
            ->create();

        expect($item->unit_price)->toBe('100.00')
            ->and($item->quantity)->toBe('2.0000')
            ->and((float) $item->tax_rate)->toBe(10.0);
    });

    it('belongs to buyer order', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        $item = BuyerOrderItem::factory()->forBuyerOrder($order)->create();

        expect($item->buyerOrder)->toBeInstanceOf(BuyerOrder::class)
            ->and($item->buyerOrder->getKey())->toBe($order->getKey());
    });

    it('can link to request item', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        $item = BuyerOrderItem::factory()
            ->forBuyerOrder($order)
            ->forRequestItem($requestItem)
            ->create();

        expect($item->requestItem)->toBeInstanceOf(RequestItem::class)
            ->and($item->requestItem->getKey())->toBe($requestItem->getKey());
    });

    it('can link to tax code', function (): void {
        $taxCode = TaxCode::factory()->recycle($this->team)->create(['rate' => 11]);

        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        $item = BuyerOrderItem::factory()
            ->forBuyerOrder($order)
            ->withTaxCode($taxCode)
            ->create();

        expect($item->taxCode)->toBeInstanceOf(TaxCode::class)
            ->and($item->tax_rate)->toBe('11.0000');
    });
});

describe('BuyerOrder Number Generation', function (): void {
    it('generates order numbers with expected format', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        // Order number should match expected format. Sequence is a factory
        // fake (see BuyerOrderFactory), not the real allocator, so it is not
        // constrained to 4 digits like the real format.
        expect($order->order_number)->toMatch('/^BO-\d{4}-\d+$/');
    });

    it('generates order number via static method', function (): void {
        $orderNumber = BuyerOrder::generateNextNumber($this->team->getKey());

        expect($orderNumber)->toMatch('/^BO-\d{4}-\d{4}$/');
    });

    it('increments order numbers sequentially when using observer', function (): void {
        // Create orders without pre-set order numbers to test auto-generation
        $order1 = BuyerOrder::create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'request_id' => $this->request->getKey(),
        ]);

        $order2 = BuyerOrder::create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'request_id' => $this->request->getKey(),
        ]);

        // Extract sequence numbers
        preg_match('/BO-\d{4}-(\d{4})/', (string) $order1->order_number, $matches1);
        preg_match('/BO-\d{4}-(\d{4})/', (string) $order2->order_number, $matches2);

        $seq1 = (int) $matches1[1];
        $seq2 = (int) $matches2[1];

        expect($seq2)->toBe($seq1 + 1);
    });
});

describe('BuyerOrder Status Methods', function (): void {
    it('correctly identifies active status', function (): void {
        $draftOrder = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->draft()
            ->create();

        $confirmedOrder = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->confirmed()
            ->create();

        $completedOrder = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->completed()
            ->create();

        $cancelledOrder = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->cancelled()
            ->create();

        expect($draftOrder->status->isActive())->toBeTrue()
            ->and($confirmedOrder->status->isActive())->toBeTrue()
            ->and($completedOrder->status->isActive())->toBeFalse()
            ->and($cancelledOrder->status->isActive())->toBeFalse();
    });

    it('correctly identifies terminal status', function (): void {
        expect(OrderStatus::COMPLETED->isTerminal())->toBeTrue()
            ->and(OrderStatus::CANCELLED->isTerminal())->toBeTrue()
            ->and(OrderStatus::DRAFT->isTerminal())->toBeFalse()
            ->and(OrderStatus::PROCESSING->isTerminal())->toBeFalse();
    });

    it('returns correct next status', function (): void {
        expect(OrderStatus::DRAFT->getNextStatus())->toBe(OrderStatus::SENT)
            ->and(OrderStatus::SENT->getNextStatus())->toBe(OrderStatus::CONFIRMED)
            ->and(OrderStatus::CONFIRMED->getNextStatus())->toBe(OrderStatus::APPROVED)
            ->and(OrderStatus::APPROVED->getNextStatus())->toBe(OrderStatus::PROCESSING)
            ->and(OrderStatus::PROCESSING->getNextStatus())->toBe(OrderStatus::SHIPPED)
            ->and(OrderStatus::SHIPPED->getNextStatus())->toBe(OrderStatus::DELIVERED)
            ->and(OrderStatus::DELIVERED->getNextStatus())->toBe(OrderStatus::INVOICED)
            ->and(OrderStatus::INVOICED->getNextStatus())->toBe(OrderStatus::COMPLETED)
            ->and(OrderStatus::COMPLETED->getNextStatus())->toBeNull()
            ->and(OrderStatus::CANCELLED->getNextStatus())->toBeNull();
    });
});

describe('BuyerOrder Totals Recalculation', function (): void {
    it('recalculates totals from items', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        // Create items with known values
        BuyerOrderItem::factory()
            ->forBuyerOrder($order)
            ->create([
                'quantity' => '10.0000',
                'unit_price_exc_tax' => '100.00',
                'tax_amount' => '10.00',
                'line_total' => '1100.00',
            ]);

        // Manually recalculate totals
        $order->recalculateTotals();
        $order->refresh();

        expect((float) $order->subtotal)->toBe(1000.0)
            ->and((float) $order->tax_total)->toBe(100.0)
            ->and((float) $order->total)->toBe(1100.0);
    });

    it('recalculates totals with multiple items', function (): void {
        $order = BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->create();

        BuyerOrderItem::factory()
            ->forBuyerOrder($order)
            ->create([
                'quantity' => '5.0000',
                'unit_price_exc_tax' => '100.00',
                'tax_amount' => '10.00',
                'line_total' => '550.00',
            ]);

        BuyerOrderItem::factory()
            ->forBuyerOrder($order)
            ->create([
                'quantity' => '3.0000',
                'unit_price_exc_tax' => '200.00',
                'tax_amount' => '20.00',
                'line_total' => '660.00',
            ]);

        $order->recalculateTotals();
        $order->refresh();

        // Item 1: 5 * 100 = 500 subtotal, 5 * 10 = 50 tax
        // Item 2: 3 * 200 = 600 subtotal, 3 * 20 = 60 tax
        // Total: 1100 subtotal, 110 tax, 1210 total
        expect((float) $order->subtotal)->toBe(1100.0)
            ->and((float) $order->tax_total)->toBe(110.0)
            ->and((float) $order->total)->toBe(1210.0);
    });
});

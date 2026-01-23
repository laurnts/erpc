<?php

declare(strict_types=1);

use App\Models\Company;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Article;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\SupplierPayment;
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

describe('SupplierInvoice Model', function (): void {
    it('can create a supplier invoice with required fields', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'invoice_number' => 'INV-001',
            ]);

        expect($invoice)->toBeInstanceOf(SupplierInvoice::class)
            ->and($invoice->status)->toBe(InvoiceStatus::DRAFT)
            ->and($invoice->type)->toBe(InvoiceType::STANDARD)
            ->and($invoice->request_id)->toBe($this->request->getKey())
            ->and($invoice->supplier_id)->toBe($this->supplier->getKey())
            ->and($invoice->invoice_number)->toBe('INV-001');
    });

    it('generates reference number on creation', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($invoice->reference_number)->toMatch('/^SI-\d{4}-\d{4}$/');
    });

    it('defaults to draft status', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($invoice->status)->toBe(InvoiceStatus::DRAFT);
    });

    it('defaults to standard type', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($invoice->type)->toBe(InvoiceType::STANDARD);
    });

    it('belongs to a request', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($invoice->request)->toBeInstanceOf(Request::class)
            ->and($invoice->request->getKey())->toBe($this->request->getKey());
    });

    it('belongs to a supplier', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($invoice->supplier)->toBeInstanceOf(Company::class)
            ->and($invoice->supplier->getKey())->toBe($this->supplier->getKey());
    });

    it('belongs to a currency', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($invoice->currency)->toBeInstanceOf(Currency::class)
            ->and($invoice->currency->getKey())->toBe($this->currency->getKey());
    });

    it('has many items', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        SupplierInvoiceItem::factory()->count(3)->recycle($invoice)->create();

        expect($invoice->items)->toHaveCount(3);
    });

    it('has many payments', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create(['total' => '1000.0000']);

        SupplierPayment::factory()
            ->count(2)
            ->recycle($this->team)
            ->recycle($invoice)
            ->create(['amount' => '200.0000']);

        expect($invoice->payments)->toHaveCount(2);
    });

    it('can have an optional supplier order reference', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'supplier_order_id' => $order->getKey(),
            ]);

        expect($invoice->supplierOrder)->toBeInstanceOf(SupplierOrder::class)
            ->and($invoice->supplierOrder->getKey())->toBe($order->getKey());
    });

    it('tracks exchange rate', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'exchange_rate' => '15000.00000000',
            ]);

        expect((float) $invoice->exchange_rate)->toBe(15000.0);
    });
});

describe('SupplierInvoice Reference Number Generation', function (): void {
    it('generates sequential reference numbers', function (): void {
        $invoice1 = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $invoice2 = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        expect($invoice1->reference_number)->toMatch('/^SI-\d{4}-0001$/')
            ->and($invoice2->reference_number)->toMatch('/^SI-\d{4}-0002$/');
    });
});

describe('SupplierInvoice Types', function (): void {
    it('can be a prepayment invoice', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->prepayment()
            ->create();

        expect($invoice->type)->toBe(InvoiceType::PREPAYMENT)
            ->and($invoice->is_credit_note)->toBeFalse();
    });

    it('can be a balance invoice', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->balance()
            ->create();

        expect($invoice->type)->toBe(InvoiceType::BALANCE);
    });

    it('can be a credit note', function (): void {
        $originalInvoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $creditNote = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->creditNote($originalInvoice)
            ->create();

        expect($creditNote->type)->toBe(InvoiceType::CREDIT_NOTE)
            ->and($creditNote->is_credit_note)->toBeTrue()
            ->and($creditNote->original_invoice_id)->toBe($originalInvoice->getKey())
            ->and($creditNote->credit_reason)->not->toBeNull();
    });

    it('can be a debit note', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->debitNote()
            ->create();

        expect($invoice->type)->toBe(InvoiceType::DEBIT_NOTE);
    });
});

describe('SupplierInvoice Status', function (): void {
    it('can mark invoice as sent', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $invoice->markAsSent();

        expect($invoice->status)->toBe(InvoiceStatus::SENT);
    });

    it('can mark invoice as partially paid', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->sent()
            ->create();

        $invoice->markAsPartiallyPaid();

        expect($invoice->status)->toBe(InvoiceStatus::PARTIAL);
    });

    it('can mark invoice as paid', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->sent()
            ->create();

        $invoice->markAsPaid();

        expect($invoice->status)->toBe(InvoiceStatus::PAID);
    });

    it('does not change status when marking cancelled invoice as paid', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->cancelled()
            ->create();

        $invoice->markAsPaid();

        expect($invoice->status)->toBe(InvoiceStatus::CANCELLED);
    });
});

describe('SupplierInvoice Amount Outstanding', function (): void {
    it('calculates amount outstanding correctly', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'total' => '1000.0000',
                'amount_paid' => '300.0000',
            ]);

        expect($invoice->amount_outstanding)->toBe(700.0);
    });

    it('returns zero when fully paid', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'total' => '1000.0000',
                'amount_paid' => '1000.0000',
            ]);

        expect($invoice->amount_outstanding)->toBe(0.0);
    });

    it('does not return negative when overpaid', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'total' => '1000.0000',
                'amount_paid' => '1200.0000',
            ]);

        expect($invoice->amount_outstanding)->toBe(0.0);
    });
});

describe('SupplierInvoice Days Overdue', function (): void {
    it('returns zero when not overdue', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->dueFuture(30)
            ->create();

        expect($invoice->days_overdue)->toBe(0);
    });

    it('calculates days overdue correctly', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->sent()
            ->duePast(10)
            ->create();

        expect($invoice->days_overdue)->toBe(10);
    });

    it('returns zero for paid invoices even if past due', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->paid()
            ->duePast(30)
            ->create();

        expect($invoice->days_overdue)->toBe(0);
    });

    it('returns zero for cancelled invoices', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->cancelled()
            ->duePast(30)
            ->create();

        expect($invoice->days_overdue)->toBe(0);
    });

    it('returns zero when due_at is null', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'due_at' => null,
                'invoice_date' => null, // Must be null to prevent observer from auto-calculating due_at
            ]);

        expect($invoice->days_overdue)->toBe(0);
    });
});

describe('SupplierInvoice Multi-Currency', function (): void {
    it('calculates base currency total with exchange rate', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'total' => '100.0000',
                'exchange_rate' => '15000.00000000',
            ]);

        expect($invoice->base_currency_total)->toBe(1500000.0);
    });

    it('uses exchange rate of 1 by default', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'total' => '1000.0000',
            ]);

        expect($invoice->base_currency_total)->toBe(1000.0);
    });
});

describe('SupplierInvoice Totals', function (): void {
    it('recalculates totals from items', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        // Create items with known values (tax exclusive)
        SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'quantity' => '10.0000',
            'unit_price' => '100.0000',
            'tax_rate' => '10.0000',
            'tax_inclusive' => false,
            'line_subtotal' => '1000.0000',
            'line_tax' => '100.0000',
            'line_total' => '1100.0000',
        ]);

        SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'quantity' => '5.0000',
            'unit_price' => '200.0000',
            'tax_rate' => '10.0000',
            'tax_inclusive' => false,
            'line_subtotal' => '1000.0000',
            'line_tax' => '100.0000',
            'line_total' => '1100.0000',
        ]);

        $invoice->recalculateTotals();

        expect((float) $invoice->subtotal)->toBe(2000.0)
            ->and((float) $invoice->tax_total)->toBe(200.0)
            ->and((float) $invoice->total)->toBe(2200.0);
    });

    it('recalculates amount paid from payments', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create(['total' => '1000.0000']);

        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($invoice)
            ->create(['amount' => '300.0000']);

        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($invoice)
            ->create(['amount' => '200.0000']);

        $invoice->recalculateAmountPaid();

        expect((float) $invoice->amount_paid)->toBe(500.0);
    });

    it('returns cost summary', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'subtotal' => '1000.0000',
                'tax_total' => '100.0000',
                'total' => '1100.0000',
                'amount_paid' => '500.0000',
                'exchange_rate' => '1.00000000',
            ]);

        $summary = $invoice->getCostSummary();

        expect($summary['subtotal'])->toBe(1000.0)
            ->and($summary['tax_total'])->toBe(100.0)
            ->and($summary['total'])->toBe(1100.0)
            ->and($summary['amount_paid'])->toBe(500.0)
            ->and($summary['amount_outstanding'])->toBe(600.0)
            ->and($summary['currency_code'])->toBe('USD')
            ->and($summary['exchange_rate'])->toBe(1.0);
    });
});

describe('SupplierInvoice Credit Note Creation', function (): void {
    it('can create a credit note from an invoice', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create([
                'invoice_number' => 'INV-001',
                'total' => '1100.0000',
            ]);

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'description' => 'Test Item',
            'quantity' => '10.0000',
            'unit_price' => '100.0000',
            'tax_rate' => '10.0000',
            'tax_inclusive' => false,
            'line_subtotal' => '1000.0000',
            'line_tax' => '100.0000',
            'line_total' => '1100.0000',
        ]);

        $creditNote = $invoice->createCreditNote([
            [
                'supplier_invoice_item_id' => $item->getKey(),
                'quantity' => 5,
                'unit_price' => 100,
            ],
        ], 'Damaged goods returned');

        expect($creditNote)->toBeInstanceOf(SupplierInvoice::class)
            ->and($creditNote->type)->toBe(InvoiceType::CREDIT_NOTE)
            ->and($creditNote->original_invoice_id)->toBe($invoice->getKey())
            ->and($creditNote->credit_reason)->toBe('Damaged goods returned')
            ->and($creditNote->invoice_number)->toBe('CN-INV-001')
            ->and($creditNote->items)->toHaveCount(1);
    });

    it('credit note has original invoice relationship', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create();

        $creditNote = $invoice->createCreditNote([
            [
                'supplier_invoice_item_id' => $item->getKey(),
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ], 'Test reason');

        expect($creditNote->originalInvoice)->toBeInstanceOf(SupplierInvoice::class)
            ->and($creditNote->originalInvoice->getKey())->toBe($invoice->getKey());
    });
});

describe('SupplierInvoiceItem Model', function (): void {
    it('can create an invoice item with required fields', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'description' => 'Test Item',
            'quantity' => '10.0000',
            'unit_price' => '50.0000',
        ]);

        expect($item)->toBeInstanceOf(SupplierInvoiceItem::class)
            ->and($item->description)->toBe('Test Item');
    });

    it('belongs to a supplier invoice', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create();

        expect($item->supplierInvoice)->toBeInstanceOf(SupplierInvoice::class)
            ->and($item->supplierInvoice->getKey())->toBe($invoice->getKey());
    });

    it('can link to supplier order item', function (): void {
        $order = SupplierOrder::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $orderItem = SupplierOrderItem::factory()->recycle($order)->create();

        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create(['supplier_order_id' => $order->getKey()]);

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'supplier_order_item_id' => $orderItem->getKey(),
        ]);

        expect($item->supplierOrderItem)->toBeInstanceOf(SupplierOrderItem::class)
            ->and($item->supplierOrderItem->getKey())->toBe($orderItem->getKey());
    });

    it('can link to request item', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();

        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'request_item_id' => $requestItem->getKey(),
        ]);

        expect($item->requestItem)->toBeInstanceOf(RequestItem::class)
            ->and($item->requestItem->getKey())->toBe($requestItem->getKey());
    });

    it('can link to article', function (): void {
        $article = Article::factory()->recycle($this->team)->create();

        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'article_id' => $article->getKey(),
        ]);

        expect($item->article)->toBeInstanceOf(Article::class)
            ->and($item->article->getKey())->toBe($article->getKey());
    });
});

describe('SupplierInvoiceItem Tax Calculations', function (): void {
    it('calculates tax exclusive line totals correctly', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = new SupplierInvoiceItem;
        $item->supplier_invoice_id = $invoice->getKey();
        $item->description = 'Test Item';
        $item->quantity = '10.0000';
        $item->unit = 'pcs';
        $item->unit_price = '100.0000';
        $item->tax_rate = '10.0000';
        $item->tax_inclusive = false;

        $item->calculateLineTotal();

        expect((float) $item->line_subtotal)->toBe(1000.0)
            ->and((float) $item->line_tax)->toBe(100.0)
            ->and((float) $item->line_total)->toBe(1100.0);
    });

    it('calculates tax inclusive line totals correctly', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = new SupplierInvoiceItem;
        $item->supplier_invoice_id = $invoice->getKey();
        $item->description = 'Test Item';
        $item->quantity = '1.0000';
        $item->unit = 'pcs';
        $item->unit_price = '110.0000';
        $item->tax_rate = '10.0000';
        $item->tax_inclusive = true;

        $item->calculateLineTotal();

        // 110 inclusive with 10% tax = 100 exc tax
        expect((float) $item->line_total)->toBe(110.0)
            ->and((float) $item->line_subtotal)->toBe(100.0)
            ->and((float) $item->line_tax)->toBe(10.0);
    });

    it('calculates unit price excluding tax', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'unit_price' => '110.0000',
            'tax_rate' => '10.0000',
            'tax_inclusive' => true,
        ]);

        expect($item->unit_price_exc_tax)->toBe(100.0);
    });

    it('calculates tax amount per unit', function (): void {
        $invoice = SupplierInvoice::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create();

        $item = SupplierInvoiceItem::factory()->recycle($invoice)->create([
            'unit_price' => '100.0000',
            'tax_rate' => '10.0000',
            'tax_inclusive' => false,
        ]);

        expect($item->tax_amount)->toBe(10.0);
    });
});

describe('Observer Registration', function (): void {
    it('has observer attribute registered', function (): void {
        $reflectionClass = new ReflectionClass(SupplierInvoice::class);
        $attributes = $reflectionClass->getAttributes(Illuminate\Database\Eloquent\Attributes\ObservedBy::class);

        expect($attributes)->toHaveCount(1);

        $observerClass = $attributes[0]->getArguments()[0];
        expect($observerClass)->toBe(App\Observers\SupplierInvoiceObserver::class);
    });
});

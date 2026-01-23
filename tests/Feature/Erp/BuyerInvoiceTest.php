<?php

declare(strict_types=1);

use App\Models\Company;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BuyerInvoice;
use App\Models\BuyerInvoiceItem;
use App\Models\BuyerOrder;
use App\Models\Currency;
use App\Models\Request;
use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->create();
    $this->actingAs($this->user);
});

describe('BuyerInvoice Model', function (): void {
    it('can create a buyer invoice with required fields', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create([
                'status' => InvoiceStatus::DRAFT,
            ]);

        expect($invoice)->toBeInstanceOf(BuyerInvoice::class)
            ->and($invoice->status)->toBe(InvoiceStatus::DRAFT);
    });

    it('generates invoice number on creation', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($invoice->invoice_number)->toMatch('/^INV-\d{4}-\d{4}$/');
    });

    it('defaults to draft status', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($invoice->status)->toBe(InvoiceStatus::DRAFT);
    });

    it('defaults to standard type', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($invoice->type)->toBe(InvoiceType::STANDARD);
    });

    it('belongs to a request', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($invoice->request)->toBeInstanceOf(Request::class)
            ->and($invoice->request->getKey())->toBe($this->request->getKey());
    });

    it('belongs to a currency', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($invoice->currency)->toBeInstanceOf(Currency::class)
            ->and($invoice->currency->getKey())->toBe($this->currency->getKey());
    });

    it('has many items', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        BuyerInvoiceItem::factory()->count(3)->forBuyerInvoice($invoice)->create();

        expect($invoice->items)->toHaveCount(3);
    });

    it('can belong to a buyer order', function (): void {
        $buyerOrder = BuyerOrder::factory()->recycle($this->team)->forRequest($this->request)->create();

        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->forBuyerOrder($buyerOrder)
            ->create();

        expect($invoice->buyerOrder)->toBeInstanceOf(BuyerOrder::class)
            ->and($invoice->buyerOrder->getKey())->toBe($buyerOrder->getKey());
    });
});

describe('BuyerInvoice Invoice Types', function (): void {
    it('can be a prepayment invoice', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->prepayment()
            ->create();

        expect($invoice->type)->toBe(InvoiceType::PREPAYMENT)
            ->and($invoice->is_prepayment)->toBeTrue()
            ->and($invoice->is_credit_note)->toBeFalse();
    });

    it('can be a balance invoice', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->balance()
            ->create();

        expect($invoice->type)->toBe(InvoiceType::BALANCE);
    });

    it('can be a credit note', function (): void {
        $originalInvoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $creditNote = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->creditNote($originalInvoice)
            ->create();

        expect($creditNote->type)->toBe(InvoiceType::CREDIT_NOTE)
            ->and($creditNote->is_credit_note)->toBeTrue()
            ->and($creditNote->original_invoice_id)->toBe($originalInvoice->getKey())
            ->and($creditNote->credit_reason)->not->toBeNull();
    });

    it('can be a debit note', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->debitNote()
            ->create();

        expect($invoice->type)->toBe(InvoiceType::DEBIT_NOTE);
    });
});

describe('BuyerInvoice Status Transitions', function (): void {
    it('can edit draft invoices', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        expect($invoice->status->canEdit())->toBeTrue();
    });

    it('can edit sent invoices', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        expect($invoice->status->canEdit())->toBeTrue();
    });

    it('cannot edit paid invoices', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->paid()
            ->create();

        expect($invoice->status->canEdit())->toBeFalse();
    });

    it('can transition from draft to sent', function (): void {
        expect(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::SENT))->toBeTrue();
    });

    it('can transition from sent to partial', function (): void {
        expect(InvoiceStatus::SENT->canTransitionTo(InvoiceStatus::PARTIAL))->toBeTrue();
    });

    it('can transition from sent to paid', function (): void {
        expect(InvoiceStatus::SENT->canTransitionTo(InvoiceStatus::PAID))->toBeTrue();
    });

    it('can transition from partial to paid', function (): void {
        expect(InvoiceStatus::PARTIAL->canTransitionTo(InvoiceStatus::PAID))->toBeTrue();
    });

    it('cannot transition from paid', function (): void {
        expect(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::CANCELLED))->toBeFalse()
            ->and(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::DRAFT))->toBeFalse();
    });

    it('marks invoice as sent correctly', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->draft()
            ->create();

        $invoice->markAsSent();

        expect($invoice->status)->toBe(InvoiceStatus::SENT)
            ->and($invoice->issued_at)->not->toBeNull()
            ->and($invoice->due_at)->not->toBeNull();
    });

    it('throws exception when marking sent from invalid status', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->paid()
            ->create();

        $invoice->markAsSent();
    })->throws(InvalidArgumentException::class);

    it('marks invoice as paid correctly', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $invoice->markAsPaid();

        expect($invoice->status)->toBe(InvoiceStatus::PAID);
    });

    it('cancels invoice correctly', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $invoice->cancel();

        expect($invoice->status)->toBe(InvoiceStatus::CANCELLED);
    });
});

describe('BuyerInvoice Credit Note Creation', function (): void {
    it('creates credit note from original invoice', function (): void {
        $originalInvoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(1000, 100, 1100)
            ->create();

        $creditNoteItems = [
            [
                'description' => 'Return item 1',
                'quantity' => 2,
                'unit_price' => 100,
                'tax_rate' => 10,
            ],
        ];

        $creditNote = $originalInvoice->createCreditNote($creditNoteItems, 'Items returned by customer');

        expect($creditNote)->toBeInstanceOf(BuyerInvoice::class)
            ->and($creditNote->type)->toBe(InvoiceType::CREDIT_NOTE)
            ->and($creditNote->original_invoice_id)->toBe($originalInvoice->getKey())
            ->and($creditNote->credit_reason)->toBe('Items returned by customer')
            ->and($creditNote->items)->toHaveCount(1)
            ->and((float) $creditNote->subtotal)->toBe(200.0)
            ->and((float) $creditNote->tax_total)->toBe(20.0)
            ->and((float) $creditNote->total)->toBe(220.0);
    });

    it('throws exception when creating credit note from credit note', function (): void {
        $originalInvoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $creditNote = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->creditNote($originalInvoice)
            ->create();

        $creditNote->createCreditNote([], 'Invalid');
    })->throws(InvalidArgumentException::class);
});

describe('BuyerInvoice Amount Calculations', function (): void {
    it('calculates amount outstanding correctly', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->withTotals(900, 100, 1000)
            ->create([
                'amount_paid' => '400.0000',
            ]);

        expect($invoice->amount_outstanding)->toBe(600.0);
    });

    it('amount outstanding is never negative', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->withTotals(900, 100, 1000)
            ->create([
                'amount_paid' => '1200.0000',
            ]);

        expect($invoice->amount_outstanding)->toBe(0.0);
    });

    it('calculates days overdue correctly', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create([
                'due_at' => now()->subDays(10),
            ]);

        expect($invoice->days_overdue)->toBe(10);
    });

    it('days overdue is zero for paid invoices', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->paid()
            ->create([
                'due_at' => now()->subDays(10),
            ]);

        expect($invoice->days_overdue)->toBe(0);
    });

    it('days overdue is zero for future due dates', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create([
                'due_at' => now()->addDays(10),
            ]);

        expect($invoice->days_overdue)->toBe(0);
    });
});

describe('BuyerInvoice Totals Recalculation', function (): void {
    it('recalculates totals from items', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        BuyerInvoiceItem::factory()
            ->forBuyerInvoice($invoice)
            ->withPricing(unitPrice: 100, quantity: 10, taxRate: 10)
            ->create();

        $invoice->recalculateTotals();
        $invoice->refresh();

        expect((float) $invoice->subtotal)->toBe(1000.0)
            ->and((float) $invoice->tax_total)->toBe(100.0)
            ->and((float) $invoice->total)->toBe(1100.0);
    });

    it('recalculates totals with multiple items', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        BuyerInvoiceItem::factory()
            ->forBuyerInvoice($invoice)
            ->withPricing(unitPrice: 100, quantity: 5, taxRate: 10)
            ->create();

        BuyerInvoiceItem::factory()
            ->forBuyerInvoice($invoice)
            ->withPricing(unitPrice: 200, quantity: 3, taxRate: 20)
            ->create();

        $invoice->recalculateTotals();
        $invoice->refresh();

        // Item 1: 5 * 100 = 500 subtotal, 50 tax
        // Item 2: 3 * 200 = 600 subtotal, 120 tax
        // Total: 1100 subtotal, 170 tax, 1270 total
        expect((float) $invoice->subtotal)->toBe(1100.0)
            ->and((float) $invoice->tax_total)->toBe(170.0)
            ->and((float) $invoice->total)->toBe(1270.0);
    });
});

describe('BuyerInvoice Number Generation', function (): void {
    it('generates invoice numbers with expected format', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        expect($invoice->invoice_number)->toMatch('/^INV-\d{4}-\d{4}$/');
    });

    it('generates invoice number via static method', function (): void {
        $invoiceNumber = BuyerInvoice::generateNextNumber($this->team->getKey());

        expect($invoiceNumber)->toMatch('/^INV-\d{4}-\d{4}$/');
    });

    it('increments invoice numbers sequentially when using observer', function (): void {
        $invoice1 = BuyerInvoice::create([
            'team_id' => $this->team->getKey(),
            'request_id' => $this->request->getKey(),
            'currency_id' => $this->currency->getKey(),
        ]);

        $invoice2 = BuyerInvoice::create([
            'team_id' => $this->team->getKey(),
            'request_id' => $this->request->getKey(),
            'currency_id' => $this->currency->getKey(),
        ]);

        preg_match('/INV-\d{4}-(\d{4})/', $invoice1->invoice_number, $matches1);
        preg_match('/INV-\d{4}-(\d{4})/', $invoice2->invoice_number, $matches2);

        $seq1 = (int) $matches1[1];
        $seq2 = (int) $matches2[1];

        expect($seq2)->toBe($seq1 + 1);
    });
});

describe('BuyerInvoice Observer Auto-Assignment', function (): void {
    it('auto-assigns team_id from authenticated user when current team is set', function (): void {
        // Set up user to have the team as their current team
        $this->user->current_team_id = $this->team->getKey();
        $this->user->save();
        $this->user->refresh();

        $invoice = BuyerInvoice::create([
            'request_id' => $this->request->getKey(),
            'currency_id' => $this->currency->getKey(),
        ]);

        expect($invoice->team_id)->toBe($this->team->getKey());
    });

    it('auto-assigns creator_id from authenticated user', function (): void {
        // Set up user to have the team as their current team
        $this->user->current_team_id = $this->team->getKey();
        $this->user->save();
        $this->user->refresh();

        $invoice = BuyerInvoice::create([
            'request_id' => $this->request->getKey(),
            'currency_id' => $this->currency->getKey(),
        ]);

        expect($invoice->creator_id)->toBe($this->user->getKey());
    });
});

describe('BuyerInvoiceItem Model', function (): void {
    it('can create item with pricing', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerInvoiceItem::factory()
            ->forBuyerInvoice($invoice)
            ->withPricing(unitPrice: 100, quantity: 2, taxRate: 10)
            ->create();

        expect($item->unit_price)->toBe('100.0000')
            ->and($item->quantity)->toBe('2.0000')
            ->and((float) $item->tax_rate)->toBe(10.0);
    });

    it('belongs to buyer invoice', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerInvoiceItem::factory()->forBuyerInvoice($invoice)->create();

        expect($item->buyerInvoice)->toBeInstanceOf(BuyerInvoice::class)
            ->and($item->buyerInvoice->getKey())->toBe($invoice->getKey());
    });

    it('can link to tax code', function (): void {
        $taxCode = TaxCode::factory()->recycle($this->team)->create(['rate' => 15]);

        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerInvoiceItem::factory()
            ->forBuyerInvoice($invoice)
            ->withTaxCode($taxCode)
            ->create();

        expect($item->taxCode)->toBeInstanceOf(TaxCode::class)
            ->and($item->tax_rate)->toBe('15.0000');
    });

    it('calculates tax inclusive pricing correctly', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->create();

        $item = BuyerInvoiceItem::factory()
            ->forBuyerInvoice($invoice)
            ->withPricing(unitPrice: 110, quantity: 1, taxRate: 10, taxInclusive: true)
            ->create();

        expect($item->tax_inclusive)->toBeTrue()
            ->and(round((float) $item->line_subtotal, 2))->toBe(100.0)
            ->and(round((float) $item->line_tax, 2))->toBe(10.0)
            ->and((float) $item->line_total)->toBe(110.0);
    });
});

describe('BuyerInvoice Status Helper Methods', function (): void {
    it('correctly identifies active status', function (): void {
        expect(InvoiceStatus::DRAFT->isActive())->toBeTrue()
            ->and(InvoiceStatus::SENT->isActive())->toBeTrue()
            ->and(InvoiceStatus::PARTIAL->isActive())->toBeTrue()
            ->and(InvoiceStatus::OVERDUE->isActive())->toBeTrue()
            ->and(InvoiceStatus::PAID->isActive())->toBeFalse()
            ->and(InvoiceStatus::CANCELLED->isActive())->toBeFalse();
    });

    it('correctly identifies terminal status', function (): void {
        expect(InvoiceStatus::PAID->isTerminal())->toBeTrue()
            ->and(InvoiceStatus::CANCELLED->isTerminal())->toBeTrue()
            ->and(InvoiceStatus::DRAFT->isTerminal())->toBeFalse();
    });

    it('correctly identifies when payments can be recorded', function (): void {
        expect(InvoiceStatus::SENT->canRecordPayment())->toBeTrue()
            ->and(InvoiceStatus::PARTIAL->canRecordPayment())->toBeTrue()
            ->and(InvoiceStatus::OVERDUE->canRecordPayment())->toBeTrue()
            ->and(InvoiceStatus::DRAFT->canRecordPayment())->toBeFalse()
            ->and(InvoiceStatus::PAID->canRecordPayment())->toBeFalse()
            ->and(InvoiceStatus::CANCELLED->canRecordPayment())->toBeFalse();
    });
});

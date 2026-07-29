<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\BuyerInvoice;
use App\Models\BuyerPayment;
use App\Models\Currency;
use App\Models\Request;
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

describe('BuyerPayment Model', function (): void {
    it('can create a buyer payment with required fields', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->bankTransfer()
            ->withAmount(500)
            ->create();

        expect($payment)->toBeInstanceOf(BuyerPayment::class)
            ->and($payment->payment_method)->toBe(PaymentMethod::BANK_TRANSFER)
            ->and((float) $payment->amount)->toBe(500.0);
    });

    it('generates payment number on creation', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->create();

        // Sequence is a factory fake (see BuyerPaymentFactory), not the real
        // allocator, so it is not constrained to 4 digits like the real format.
        expect($payment->payment_number)->toMatch('/^PAY-\d{4}-\d+$/');
    });

    it('belongs to a buyer invoice', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->create();

        expect($payment->buyerInvoice)->toBeInstanceOf(BuyerInvoice::class)
            ->and($payment->buyerInvoice->getKey())->toBe($invoice->getKey());
    });
});

describe('BuyerPayment Payment Methods', function (): void {
    it('can be a bank transfer', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->bankTransfer()
            ->create();

        expect($payment->payment_method)->toBe(PaymentMethod::BANK_TRANSFER);
    });

    it('can be cash', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->cash()
            ->create();

        expect($payment->payment_method)->toBe(PaymentMethod::CASH);
    });

    it('can be a check', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->check()
            ->create();

        expect($payment->payment_method)->toBe(PaymentMethod::CHECK);
    });

    it('can be a letter of credit', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->letterOfCredit()
            ->create();

        expect($payment->payment_method)->toBe(PaymentMethod::LC);
    });
});

describe('BuyerPayment Updates Invoice', function (): void {
    it('updates invoice amount_paid after payment created', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->withAmount(500)
            ->create();

        $invoice->refresh();

        expect((float) $invoice->amount_paid)->toBe(500.0);
    });

    it('marks invoice as partially paid when partial payment made', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->withAmount(500)
            ->create();

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::PARTIAL);
    });

    it('marks invoice as paid when full payment made', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->withAmount(1000)
            ->create();

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::PAID);
    });

    it('marks invoice as paid when multiple payments reach total', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->withAmount(600)
            ->create();

        $invoice->refresh();
        expect($invoice->status)->toBe(InvoiceStatus::PARTIAL);

        BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->withAmount(400)
            ->create();

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::PAID)
            ->and((float) $invoice->amount_paid)->toBe(1000.0);
    });

    it('updates invoice when payment is updated', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->withAmount(500)
            ->create();

        $invoice->refresh();
        expect((float) $invoice->amount_paid)->toBe(500.0);

        $payment->amount = '700.0000';
        $payment->save();

        $invoice->refresh();
        expect((float) $invoice->amount_paid)->toBe(700.0);
    });

    it('updates invoice when payment is deleted', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->withAmount(1000)
            ->create();

        $invoice->refresh();
        expect($invoice->status)->toBe(InvoiceStatus::PAID);

        $payment->delete();

        $invoice->refresh();
        expect((float) $invoice->amount_paid)->toBe(0.0)
            ->and($invoice->status)->not->toBe(InvoiceStatus::PAID);
    });
});

describe('BuyerPayment Number Generation', function (): void {
    it('generates payment numbers with expected format', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->create();

        // Sequence is a factory fake (see BuyerPaymentFactory), not the real
        // allocator, so it is not constrained to 4 digits like the real format.
        expect($payment->payment_number)->toMatch('/^PAY-\d{4}-\d+$/');
    });

    it('generates payment number via static method', function (): void {
        $paymentNumber = BuyerPayment::generateNextNumber($this->team->getKey());

        expect($paymentNumber)->toMatch('/^PAY-\d{4}-\d{4}$/');
    });

    it('increments payment numbers sequentially when using observer', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment1 = BuyerPayment::create([
            'team_id' => $this->team->getKey(),
            'buyer_invoice_id' => $invoice->getKey(),
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'amount' => '100.0000',
        ]);

        $payment2 = BuyerPayment::create([
            'team_id' => $this->team->getKey(),
            'buyer_invoice_id' => $invoice->getKey(),
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'amount' => '100.0000',
        ]);

        preg_match('/PAY-\d{4}-(\d{4})/', (string) $payment1->payment_number, $matches1);
        preg_match('/PAY-\d{4}-(\d{4})/', (string) $payment2->payment_number, $matches2);

        $seq1 = (int) $matches1[1];
        $seq2 = (int) $matches2[1];

        expect($seq2)->toBe($seq1 + 1);
    });
});

describe('BuyerPayment Observer Auto-Assignment', function (): void {
    it('auto-assigns team_id from authenticated user when current team is set', function (): void {
        // Set up user to have the team as their current team
        $this->user->current_team_id = $this->team->getKey();
        $this->user->save();
        $this->user->refresh();

        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::create([
            'buyer_invoice_id' => $invoice->getKey(),
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'amount' => '100.0000',
        ]);

        expect($payment->team_id)->toBe($this->team->getKey());
    });

    it('auto-assigns creator_id from authenticated user', function (): void {
        // Set up user to have the team as their current team
        $this->user->current_team_id = $this->team->getKey();
        $this->user->save();
        $this->user->refresh();

        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::create([
            'buyer_invoice_id' => $invoice->getKey(),
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'amount' => '100.0000',
        ]);

        expect($payment->creator_id)->toBe($this->user->getKey());
    });

    it('auto-assigns payment_date if not provided', function (): void {
        // Set up user to have the team as their current team
        $this->user->current_team_id = $this->team->getKey();
        $this->user->save();
        $this->user->refresh();

        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::create([
            'buyer_invoice_id' => $invoice->getKey(),
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'amount' => '100.0000',
        ]);

        expect($payment->payment_date)->not->toBeNull();
    });
});

describe('BuyerPayment Factory Helpers', function (): void {
    it('can create full payment for invoice', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        $payment = BuyerPayment::factory()
            ->fullPayment($invoice)
            ->create();

        expect((float) $payment->amount)->toBe((float) $invoice->total);
    });

    it('can create partial payment for invoice', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->withTotals(900, 100, 1000)
            ->create();

        $payment = BuyerPayment::factory()
            ->partialPayment($invoice, 50)
            ->create();

        expect((float) $payment->amount)->toBe(500.0);
    });
});

describe('BuyerPayment Display Text', function (): void {
    it('returns formatted display text', function (): void {
        $invoice = BuyerInvoice::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withCurrency($this->currency)
            ->sent()
            ->create();

        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($invoice)
            ->bankTransfer()
            ->withAmount(1000)
            ->create();

        expect($payment->display_text)->toContain($payment->payment_number)
            ->and($payment->display_text)->toContain('1,000.00')
            ->and($payment->display_text)->toContain('Bank Transfer');
    });
});

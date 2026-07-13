<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierInvoice;
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
    $this->invoice = SupplierInvoice::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->recycle($this->supplier)
        ->recycle($this->currency)
        ->sent()
        ->create([
            'total' => '1000.0000',
            'amount_paid' => '0.0000',
        ]);
    $this->actingAs($this->user);
});

describe('SupplierPayment Model', function (): void {
    it('can create a supplier payment with required fields', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create([
                'amount' => '500.0000',
                'payment_date' => now(),
            ]);

        expect($payment)->toBeInstanceOf(SupplierPayment::class)
            ->and((float) $payment->amount)->toBe(500.0)
            ->and($payment->supplier_invoice_id)->toBe($this->invoice->getKey());
    });

    it('generates payment number on creation', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create();

        expect($payment->payment_number)->toMatch('/^SP-\d{4}-\d{4}$/');
    });

    it('belongs to a supplier invoice', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create();

        expect($payment->supplierInvoice)->toBeInstanceOf(SupplierInvoice::class)
            ->and($payment->supplierInvoice->getKey())->toBe($this->invoice->getKey());
    });

    it('sets team_id and creator_id from auth', function (): void {
        // Set up user's current team properly
        $this->user->current_team_id = $this->team->getKey();
        $this->user->save();

        // Create a new payment directly without factory defaults for team/creator
        $payment = new SupplierPayment;
        $payment->supplier_invoice_id = $this->invoice->getKey();
        $payment->amount = '500.0000';
        $payment->payment_date = now();
        $payment->payment_method = \App\Enums\PaymentMethod::BANK_TRANSFER;
        $payment->save();

        expect($payment->team_id)->toBe($this->team->getKey())
            ->and($payment->creator_id)->toBe($this->user->getKey());
    });
});

describe('SupplierPayment Number Generation', function (): void {
    it('generates sequential payment numbers', function (): void {
        $payment1 = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create();

        $payment2 = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create();

        expect($payment1->payment_number)->toMatch('/^SP-\d{4}-0001$/')
            ->and($payment2->payment_number)->toMatch('/^SP-\d{4}-0002$/');
    });
});

describe('SupplierPayment Methods', function (): void {
    it('can be a bank transfer payment', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->bankTransfer()
            ->create();

        expect($payment->payment_method)->toBe(PaymentMethod::BANK_TRANSFER);
    });

    it('can be a cash payment', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->cash()
            ->create();

        expect($payment->payment_method)->toBe(PaymentMethod::CASH);
    });

    it('can be a check payment', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->check()
            ->create();

        expect($payment->payment_method)->toBe(PaymentMethod::CHECK);
    });

    it('can be a letter of credit payment', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->letterOfCredit()
            ->create();

        expect($payment->payment_method)->toBe(PaymentMethod::LC);
    });
});

describe('SupplierPayment Invoice Integration', function (): void {
    it('updates invoice amount_paid when payment is created', function (): void {
        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '300.0000']);

        $this->invoice->refresh();

        expect((float) $this->invoice->amount_paid)->toBe(300.0);
    });

    it('updates invoice status to partial when partially paid', function (): void {
        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '500.0000']);

        $this->invoice->refresh();

        expect($this->invoice->status)->toBe(InvoiceStatus::PARTIAL)
            ->and((float) $this->invoice->amount_paid)->toBe(500.0);
    });

    it('updates invoice status to paid when fully paid', function (): void {
        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '1000.0000']);

        $this->invoice->refresh();

        expect($this->invoice->status)->toBe(InvoiceStatus::PAID)
            ->and((float) $this->invoice->amount_paid)->toBe(1000.0);
    });

    it('accumulates multiple payments', function (): void {
        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '300.0000']);

        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '200.0000']);

        $this->invoice->refresh();

        expect((float) $this->invoice->amount_paid)->toBe(500.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::PARTIAL);
    });

    it('marks invoice as paid when total payments equal invoice total', function (): void {
        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '400.0000']);

        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '600.0000']);

        $this->invoice->refresh();

        expect((float) $this->invoice->amount_paid)->toBe(1000.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::PAID);
    });

    it('recalculates amount when payment is updated', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '300.0000']);

        $this->invoice->refresh();
        expect((float) $this->invoice->amount_paid)->toBe(300.0);

        $payment->amount = '500.0000';
        $payment->save();

        $this->invoice->refresh();
        expect((float) $this->invoice->amount_paid)->toBe(500.0);
    });

    it('recalculates amount when payment is deleted', function (): void {
        $payment1 = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '300.0000']);

        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '200.0000']);

        $this->invoice->refresh();
        expect((float) $this->invoice->amount_paid)->toBe(500.0);

        $payment1->delete();

        $this->invoice->refresh();
        expect((float) $this->invoice->amount_paid)->toBe(200.0);
    });
});

describe('SupplierPayment Formatted Amount', function (): void {
    it('returns formatted amount in invoice currency', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '1234.5600']);

        expect($payment->formatted_amount)->toContain('1,234.56');
    });
});

describe('SupplierPayment Reference', function (): void {
    it('can have a reference number', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->withReference('TRF-12345')
            ->create();

        expect($payment->reference_number)->toBe('TRF-12345');
    });

    it('can have notes', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['notes' => 'Wire transfer from account']);

        expect($payment->notes)->toBe('Wire transfer from account');
    });
});

describe('SupplierPayment Soft Delete', function (): void {
    it('soft deletes payment', function (): void {
        $payment = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create();

        $payment->delete();

        expect($payment->deleted_at)->not->toBeNull();
        expect(SupplierPayment::withTrashed()->find($payment->getKey()))->not->toBeNull();
    });

    it('updates invoice amount when payment is soft deleted', function (): void {
        SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '300.0000']);

        $payment2 = SupplierPayment::factory()
            ->recycle($this->team)
            ->recycle($this->invoice)
            ->create(['amount' => '200.0000']);

        $this->invoice->refresh();
        expect((float) $this->invoice->amount_paid)->toBe(500.0);

        $payment2->delete();

        $this->invoice->refresh();
        expect((float) $this->invoice->amount_paid)->toBe(300.0);
    });
});

describe('Observer Registration', function (): void {
    it('has observer attribute registered', function (): void {
        $reflectionClass = new ReflectionClass(SupplierPayment::class);
        $attributes = $reflectionClass->getAttributes(Illuminate\Database\Eloquent\Attributes\ObservedBy::class);

        expect($attributes)->toHaveCount(1);

        $observerClass = $attributes[0]->getArguments()[0];
        expect($observerClass)->toBe(App\Observers\SupplierPaymentObserver::class);
    });
});

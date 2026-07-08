<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
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

    $this->invoice = BuyerInvoice::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->withCurrency($this->currency)
        ->sent()
        ->withTotals(900, 100, 1000)
        ->create();
});

describe('Confirmed payments reduce outstanding', function (): void {
    it('reduces amount_outstanding when a confirmed payment is recorded', function (): void {
        BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(400)
            ->create(['status' => PaymentStatus::Confirmed->value]);

        $this->invoice->refresh();

        expect((float) $this->invoice->amount_paid)->toBe(400.0)
            ->and($this->invoice->amount_outstanding)->toBe(600.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::PARTIAL);
    });

    it('defaults new payments to confirmed status', function (): void {
        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(100)
            ->create();

        expect($payment->status)->toBe(PaymentStatus::Confirmed);
    });
});

describe('Pending payments do not reduce outstanding', function (): void {
    it('does NOT reduce amount_outstanding while a payment is pending', function (): void {
        BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(400)
            ->create(['status' => PaymentStatus::Pending->value]);

        $this->invoice->refresh();

        expect((float) $this->invoice->amount_paid)->toBe(0.0)
            ->and($this->invoice->amount_outstanding)->toBe(1000.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::SENT);
    });

    it('only counts confirmed payments when both pending and confirmed exist', function (): void {
        BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(300)
            ->create(['status' => PaymentStatus::Confirmed->value]);

        BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(500)
            ->create(['status' => PaymentStatus::Pending->value]);

        $this->invoice->refresh();

        expect((float) $this->invoice->amount_paid)->toBe(300.0)
            ->and($this->invoice->amount_outstanding)->toBe(700.0);
    });

    it('scopeConfirmed returns only confirmed payments', function (): void {
        BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(300)
            ->create(['status' => PaymentStatus::Confirmed->value]);

        BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(500)
            ->create(['status' => PaymentStatus::Pending->value]);

        expect($this->invoice->payments()->confirmed()->count())->toBe(1)
            ->and((float) $this->invoice->payments()->confirmed()->sum('amount'))->toBe(300.0);
    });
});

describe('Confirming a pending payment', function (): void {
    it('reduces amount_outstanding once confirmed via confirm()', function (): void {
        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(1000)
            ->create(['status' => PaymentStatus::Pending->value]);

        $this->invoice->refresh();
        expect($this->invoice->amount_outstanding)->toBe(1000.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::SENT);

        $payment->confirm($this->user);

        $this->invoice->refresh();
        expect($payment->status)->toBe(PaymentStatus::Confirmed)
            ->and($payment->confirmed_by_id)->toBe($this->user->getKey())
            ->and($payment->confirmed_at)->not->toBeNull()
            ->and((float) $this->invoice->amount_paid)->toBe(1000.0)
            ->and($this->invoice->amount_outstanding)->toBe(0.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::PAID);
    });

    it('logs a status change when a pending payment is confirmed', function (): void {
        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(500)
            ->create(['status' => PaymentStatus::Pending->value]);

        ActivityLog::query()->delete();

        $payment->confirm($this->user);

        $activity = ActivityLog::query()->latest('id')->first();

        expect($activity)->not->toBeNull()
            ->and($activity->event)->toBe('updated')
            ->and($activity->properties->get('attributes'))->toMatchArray(['status' => PaymentStatus::Confirmed->value])
            ->and($activity->properties->get('old'))->toMatchArray(['status' => PaymentStatus::Pending->value]);
    });
});

describe('Deleting a confirmed payment', function (): void {
    it('restores amount_outstanding when a confirmed payment is deleted', function (): void {
        $payment = BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(1000)
            ->create(['status' => PaymentStatus::Confirmed->value]);

        $this->invoice->refresh();
        expect($this->invoice->status)->toBe(InvoiceStatus::PAID)
            ->and($this->invoice->amount_outstanding)->toBe(0.0);

        $payment->delete();

        $this->invoice->refresh();
        expect((float) $this->invoice->amount_paid)->toBe(0.0)
            ->and($this->invoice->amount_outstanding)->toBe(1000.0)
            ->and($this->invoice->status)->not->toBe(InvoiceStatus::PAID);
    });
});

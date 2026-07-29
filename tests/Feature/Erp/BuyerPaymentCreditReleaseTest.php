<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->actingAs($this->user);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'credit_status' => true,
        'credit_limit' => '1000.00',
        'available_credit' => '1000.00',
        'credit_used' => '0.00',
    ]);

    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();

    $this->order = BuyerOrder::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->forRequest($this->request)
        ->forBuyer($this->buyer)
        ->create(['status' => OrderStatus::DRAFT, 'total' => '400.00']);
    $this->order->confirm();

    $this->invoice = BuyerInvoice::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->withCurrency($this->currency)
        ->forBuyerOrder($this->order)
        ->sent()
        ->withTotals(400, 0, 400)
        ->create();
});

it('releases credit when a payment is recorded against the invoice', function (): void {
    BuyerPayment::factory()
        ->recycle($this->team)
        ->forBuyerInvoice($this->invoice)
        ->create([
            'amount' => '400.0000',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

    expect($this->buyer->fresh()->derived_available_credit)->toBe(1000.0)
        ->and($this->buyer->fresh()->credit_exposure)->toBe(0.0)
        ->and((float) $this->order->refresh()->credit_released)->toBe(400.00);
});

it('releases proportional credit on a partial payment', function (): void {
    BuyerPayment::factory()
        ->recycle($this->team)
        ->forBuyerInvoice($this->invoice)
        ->create([
            'amount' => '100.0000',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

    expect($this->buyer->fresh()->derived_available_credit)->toBe(700.0)
        ->and((float) $this->order->refresh()->credit_released)->toBe(100.00);
});

it('re-reserves credit when a payment is deleted', function (): void {
    $payment = BuyerPayment::factory()
        ->recycle($this->team)
        ->forBuyerInvoice($this->invoice)
        ->create([
            'amount' => '400.0000',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

    $payment->delete();

    expect($this->buyer->fresh()->derived_available_credit)->toBe(600.0)
        ->and((float) $this->order->refresh()->credit_released)->toBe(0.00);
});

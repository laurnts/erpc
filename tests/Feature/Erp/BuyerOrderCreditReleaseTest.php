<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerCreditUsageHistory;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
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
});

/**
 * @param  array<string, mixed>  $attributes
 */
function creditReleaseOrder(Tests\TestCase $test, array $attributes = []): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle($test->team)
        ->recycle($test->user)
        ->forRequest($test->request)
        ->forBuyer($test->buyer)
        ->create(array_merge(['status' => OrderStatus::DRAFT, 'total' => '400.00'], $attributes));
}

function creditReleaseInvoice(Tests\TestCase $test, BuyerOrder $order, string $amountPaid): BuyerInvoice
{
    return BuyerInvoice::factory()
        ->recycle($test->team)
        ->forRequest($test->request)
        ->withCurrency($test->currency)
        ->forBuyerOrder($order)
        ->sent()
        ->withTotals(400, 0, 400)
        ->create(['amount_paid' => $amountPaid]);
}

it('releases credit up to the amount paid on the invoice', function (): void {
    $order = creditReleaseOrder($this);
    $order->confirm();

    $this->buyer->refresh();
    expect((float) $this->buyer->available_credit)->toBe(600.00);

    $invoice = creditReleaseInvoice($this, $order, '150.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $this->buyer->refresh();
    $order->refresh();

    expect((float) $this->buyer->available_credit)->toBe(750.00)
        ->and((float) $this->buyer->credit_used)->toBe(250.00)
        ->and((float) $order->credit_released)->toBe(150.00)
        ->and(
            BuyerCreditUsageHistory::query()
                ->where('related_type', BuyerOrder::class)
                ->where('related_id', $order->getKey())
                ->where('transaction_type', 'credit')
                ->exists()
        )->toBeTrue();
});

it('caps release at the order total on overpayment', function (): void {
    $order = creditReleaseOrder($this);
    $order->confirm();

    $invoice = creditReleaseInvoice($this, $order, '500.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $this->buyer->refresh();
    $order->refresh();

    expect((float) $this->buyer->available_credit)->toBe(1000.00)
        ->and((float) $this->buyer->credit_used)->toBe(0.00)
        ->and((float) $order->credit_released)->toBe(400.00);
});

it('does not release credit for an order that never reserved it', function (): void {
    $this->buyer->update(['credit_status' => false]);

    $order = creditReleaseOrder($this);
    $order->confirm(); // credit_status false → no reservation, no debit row

    $invoice = creditReleaseInvoice($this, $order, '400.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $this->buyer->refresh();
    $order->refresh();

    expect((float) $this->buyer->available_credit)->toBe(1000.00)
        ->and((float) $order->credit_released)->toBe(0.00);
});

it('does not reconcile credit once the order is cancelled', function (): void {
    $order = creditReleaseOrder($this);
    $order->confirm(); // reserves 400 → available 600
    $order->cancel(); // restores remainder → available 1000, credit_released = 400

    $this->buyer->refresh();
    expect((float) $this->buyer->available_credit)->toBe(1000.00);

    // A late invoice showing no outstanding balance must NOT re-reserve credit.
    $invoice = creditReleaseInvoice($this, $order, '0.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $this->buyer->refresh();
    expect((float) $this->buyer->available_credit)->toBe(1000.00)
        ->and((float) $this->buyer->credit_used)->toBe(0.00);
});

it('restores only the unreleased remainder when cancelling after a partial payment', function (): void {
    $order = creditReleaseOrder($this);
    $order->confirm();

    $invoice = creditReleaseInvoice($this, $order, '150.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $order->refresh();
    $order->cancel();

    $this->buyer->refresh();

    expect((float) $this->buyer->available_credit)->toBe(1000.00)
        ->and((float) $this->buyer->credit_used)->toBe(0.00)
        ->and($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
});

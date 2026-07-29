<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_status' => true,
        'credit_limit' => 100000,
        // available_credit does not auto-derive from credit_limit (no observer
        // syncs it); every other confirm()-exercising test in this suite sets
        // it explicitly alongside credit_limit — see tests/Feature/Erp/BuyerOrderTest.php.
        'available_credit' => 100000,
    ]);
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

function confirmedOrder(float $total, bool $useCredit = true): BuyerOrder
{
    $order = BuyerOrder::factory()
        ->recycle(test()->team)
        ->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')
        ->for(test()->request)
        ->create(['status' => OrderStatus::DRAFT, 'total' => $total]);

    $order->confirm($useCredit);

    return $order->refresh();
}

it('stamps credit_reserved_at when an order reserves credit', function (): void {
    $order = confirmedOrder(5000);

    expect($order->credit_reserved_at)->not->toBeNull();
});

it('leaves credit_reserved_at null when credit was not used', function (): void {
    $order = confirmedOrder(5000, useCredit: false);

    expect($order->credit_reserved_at)->toBeNull();
});

it('leaves credit_reserved_at null when the buyer has credit disabled', function (): void {
    $this->buyer->update(['credit_status' => false]);

    $order = confirmedOrder(5000);

    expect($order->credit_reserved_at)->toBeNull();
});

it('reports hasReservedCredit from the column', function (): void {
    $reserved = confirmedOrder(5000);
    $unreserved = confirmedOrder(5000, useCredit: false);

    expect($reserved->hasReservedCredit())->toBeTrue()
        ->and($unreserved->hasReservedCredit())->toBeFalse();
});

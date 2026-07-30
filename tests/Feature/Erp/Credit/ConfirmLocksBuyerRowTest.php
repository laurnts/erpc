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
        'credit_limit' => 1000,
    ]);
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

function creditOrder(array $attributes): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle(test()->team)
        ->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')
        ->for(test()->request)
        ->create($attributes);
}

/*
 * These tests cannot exercise real concurrency — the suite runs
 * single-process against a transactional test database, so two
 * confirm() calls can never truly overlap. What they can prove is the part
 * that actually matters for correctness: confirm() must derive exposure from
 * a freshly-locked Company row, not from whatever Company instance happened
 * to be attached to $this->buyer beforehand. A stale in-memory instance is
 * exactly what a concurrent request would be holding — it read the buyer
 * before another confirmation committed its reservation. If confirm() ever
 * regresses to using that stale instance instead of the locked one, this
 * test fails without needing two processes to demonstrate it.
 */
it('checks credit against a freshly-locked buyer row, not a stale cached instance', function (): void {
    // Force-cache derived_available_credit on a Company instance while the
    // buyer still has its full limit available — this models a Company
    // loaded earlier in a request, before a concurrent confirmation reserved
    // credit against it.
    $staleBuyer = Company::query()->whereKey($this->buyer->getKey())->firstOrFail();
    expect($staleBuyer->derived_available_credit)->toBe(1000.0);

    // Now a "concurrent" confirmation commits, reserving 950 of the limit.
    // $staleBuyer's cached attribute does not know about this.
    creditOrder([
        'status' => OrderStatus::CONFIRMED,
        'total' => 950,
        'credit_released' => 0,
        'credit_reserved_at' => now(),
    ]);

    $order = creditOrder(['status' => OrderStatus::DRAFT, 'total' => 100]);
    // Attach the stale instance as the relation confirm() reads from — this
    // is the only way, in a single process, to reproduce "the in-memory
    // buyer disagrees with the committed database".
    $order->setRelation('buyer', $staleBuyer);

    // True available credit is now 1000 - 950 = 50, less than the 100
    // requested. If confirm() used $staleBuyer's cached 1000.0 instead of
    // locking a fresh row, this would wrongly succeed.
    expect(fn () => $order->confirm())->toThrow(InvalidArgumentException::class);

    expect($order->refresh()->status)->toBe(OrderStatus::DRAFT)
        ->and($order->credit_reserved_at)->toBeNull();
});

it('still confirms when the freshly-locked buyer row has enough credit despite a stale cached instance', function (): void {
    // Same staleness setup, but this time true exposure leaves enough room.
    $staleBuyer = Company::query()->whereKey($this->buyer->getKey())->firstOrFail();
    expect($staleBuyer->derived_available_credit)->toBe(1000.0);

    creditOrder([
        'status' => OrderStatus::CONFIRMED,
        'total' => 100,
        'credit_released' => 0,
        'credit_reserved_at' => now(),
    ]);

    $order = creditOrder(['status' => OrderStatus::DRAFT, 'total' => 100]);
    $order->setRelation('buyer', $staleBuyer);

    // True available credit is 1000 - 100 = 900, comfortably covering 100.
    $order->confirm();

    expect($order->refresh()->status)->toBe(OrderStatus::CONFIRMED)
        ->and($order->credit_reserved_at)->not->toBeNull();
});

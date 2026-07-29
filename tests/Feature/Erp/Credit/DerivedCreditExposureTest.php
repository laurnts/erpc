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
    ]);
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

function orderWith(array $attributes): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle(test()->team)
        ->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')
        ->for(test()->request)
        ->create($attributes);
}

it('reports zero exposure with no orders', function (): void {
    expect($this->buyer->credit_exposure)->toBe(0.0);
});

it('counts a confirmed reserving order at its unreleased amount', function (): void {
    orderWith([
        'status' => OrderStatus::CONFIRMED,
        'total' => 5000,
        'credit_released' => 0,
        'credit_reserved_at' => now(),
    ]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(5000.0);
});

it('nets off partially released credit', function (): void {
    orderWith([
        'status' => OrderStatus::CONFIRMED,
        'total' => 5000,
        'credit_released' => 2000,
        'credit_reserved_at' => now(),
    ]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(3000.0);
});

it('ignores orders that never reserved credit', function (): void {
    orderWith([
        'status' => OrderStatus::CONFIRMED,
        'total' => 5000,
        'credit_released' => 0,
        'credit_reserved_at' => null,
    ]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(0.0);
});

it('ignores orders that are not confirmed', function (): void {
    orderWith([
        'status' => OrderStatus::DRAFT,
        'total' => 5000,
        'credit_released' => 0,
        'credit_reserved_at' => now(),
    ]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(0.0);
});

it('ignores soft-deleted orders', function (): void {
    $order = orderWith([
        'status' => OrderStatus::CONFIRMED,
        'total' => 5000,
        'credit_released' => 0,
        'credit_reserved_at' => now(),
    ]);
    $order->delete();

    expect($this->buyer->fresh()->credit_exposure)->toBe(0.0);
});

it('sums several orders', function (): void {
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 5000, 'credit_released' => 0, 'credit_reserved_at' => now()]);
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 2500, 'credit_released' => 500, 'credit_reserved_at' => now()]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(7000.0);
});

it('derives available credit from the limit', function (): void {
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 30000, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    expect($this->buyer->fresh()->derived_available_credit)->toBe(70000.0);
});

it('never reports negative available credit', function (): void {
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 150000, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    expect($this->buyer->fresh()->derived_available_credit)->toBe(0.0);
});

it('exposes the same value through the query scope', function (): void {
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 4200, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    $row = Company::withCreditExposure()->whereKey($this->buyer->getKey())->sole();

    expect((float) $row->credit_exposure)->toBe(4200.0);
});

it('sorts by exposure through the scope', function (): void {
    $other = Company::factory()->buyer()->recycle($this->team)->create(['credit_limit' => 100000]);
    $otherRequest = Request::factory()->recycle($this->team)->recycle($other)->create();

    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 100, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    BuyerOrder::factory()
        ->recycle($this->team)->recycle($this->currency)
        ->for($other, 'buyer')->for($otherRequest)
        ->create(['status' => OrderStatus::CONFIRMED, 'total' => 900, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    $ordered = Company::withCreditExposure()
        ->whereIn('id', [$this->buyer->getKey(), $other->getKey()])
        ->orderByDesc('credit_exposure')
        ->pluck('id')
        ->all();

    expect($ordered[0])->toBe($other->getKey());
});

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
        'credit_used' => 0,
    ]);
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
});

function exposureOrder(float $total, float $released = 0): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle(test()->team)->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')->for(test()->request)
        ->create([
            'status' => OrderStatus::CONFIRMED,
            'total' => $total,
            'credit_released' => $released,
            'credit_reserved_at' => now(),
        ]);
}

it('succeeds when stored and derived agree', function (): void {
    exposureOrder(5000);
    $this->buyer->update(['credit_used' => 5000]);

    $this->artisan('erp:reconcile-credit-exposure')->assertExitCode(0);
});

it('fails and names the buyer when they disagree', function (): void {
    exposureOrder(5000);
    $this->buyer->update(['credit_used' => 4200]);

    $this->artisan('erp:reconcile-credit-exposure')
        ->expectsOutputToContain($this->buyer->name)
        ->assertExitCode(1);
});

it('tolerates sub-cent differences', function (): void {
    exposureOrder(5000);
    $this->buyer->update(['credit_used' => 5000.004]);

    $this->artisan('erp:reconcile-credit-exposure')->assertExitCode(0);
});

<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerCreditUsageHistory;
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
        'available_credit' => 100000,
    ]);
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
});

function draftOrder(float $total): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle(test()->team)->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')->for(test()->request)
        ->create(['status' => OrderStatus::DRAFT, 'total' => $total]);
}

it('does not touch the credit_used column on confirm', function (): void {
    draftOrder(5000)->confirm();

    expect((float) $this->buyer->fresh()->credit_used)->toBe(0.0);
});

it('reflects the confirmation in derived exposure instead', function (): void {
    draftOrder(5000)->confirm();

    expect($this->buyer->fresh()->credit_exposure)->toBe(5000.0);
});

it('still writes an audit trail row on confirm', function (): void {
    $order = draftOrder(5000);
    $order->confirm();

    expect(BuyerCreditUsageHistory::query()
        ->where('related_id', $order->getKey())
        ->where('transaction_type', 'debit')
        ->exists())->toBeTrue();
});

it('drops exposure back to zero when credit is restored', function (): void {
    $order = draftOrder(5000);
    $order->confirm();
    $order->refresh()->restoreCredit();

    expect($this->buyer->fresh()->credit_exposure)->toBe(0.0);
});

it('refuses an order that exceeds derived available credit', function (): void {
    draftOrder(90000)->confirm();

    expect(fn (): mixed => draftOrder(20000)->confirm())
        ->toThrow(InvalidArgumentException::class, 'Insufficient credit');
});

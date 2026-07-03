<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->actingAs($this->user);
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
});

it('allows marking a draft buyer order as sent', function (): void {
    $order = BuyerOrder::factory()
        ->recycle($this->team)
        ->forBuyer($this->buyer)
        ->forRequest($this->request)
        ->draft()
        ->create();

    $order->markAsSent();

    expect($order->status)->toBe(OrderStatus::SENT);
});

it('does not let an approved buyer order be marked as sent (closes the backward credit re-reduction backdoor)', function (): void {
    $order = BuyerOrder::factory()
        ->recycle($this->team)
        ->forBuyer($this->buyer)
        ->forRequest($this->request)
        ->create(['status' => OrderStatus::APPROVED]);

    expect(fn () => $order->markAsSent())->toThrow(InvalidArgumentException::class);
    expect($order->fresh()->status)->toBe(OrderStatus::APPROVED);
});

<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
    $this->actingAs($this->user);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'credit_status' => true,
        'credit_limit' => '10000.00',
        'available_credit' => '10000.00',
        'credit_used' => '0.00',
    ]);
    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();

    $this->order = BuyerOrder::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->forRequest($this->request)
        ->forBuyer($this->buyer)
        ->create([
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
            'total' => '220.00',
            'payment_terms_days' => 14,
        ]);

    BuyerOrderItem::factory()
        ->recycle($this->team)
        ->create([
            'buyer_order_id' => $this->order->getKey(),
            'description' => 'Widget',
            'quantity' => '2.0000',
            'unit_price' => '100.0000',
            'tax_rate' => '10.0000',
            'is_tax_inclusive' => false,
            'sort_order' => 0,
        ]);
});

it('issues a sent invoice from a confirmed order with a termin', function (): void {
    $invoice = BuyerInvoice::issueFromOrder($this->order);

    expect($invoice->status)->toBe(InvoiceStatus::SENT)
        ->and($invoice->buyer_order_id)->toBe($this->order->getKey())
        ->and($invoice->request_id)->toBe($this->request->getKey())
        ->and($invoice->currency_id)->toBe($this->currency->getKey())
        ->and($invoice->net_days)->toBe(14)
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($invoice->due_at?->toDateString())->toBe($invoice->issued_at->copy()->addDays(14)->toDateString())
        ->and($invoice->items)->toHaveCount(1)
        ->and((float) $invoice->total)->toBe(220.0);
});

it('refuses to issue from an unconfirmed order', function (): void {
    $this->order->update(['status' => OrderStatus::DRAFT]);

    BuyerInvoice::issueFromOrder($this->order);
})->throws(InvalidArgumentException::class);

it('refuses to issue a second active invoice for the same order', function (): void {
    BuyerInvoice::issueFromOrder($this->order);

    BuyerInvoice::issueFromOrder($this->order->refresh());
})->throws(InvalidArgumentException::class);

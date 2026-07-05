<?php

declare(strict_types=1);

use App\Actions\Erp\SendSupplierOrderToSupplier;
use App\Enums\OrderStatus;
use App\Enums\SupplierOrderSendOutcome;
use App\Mail\Erp\PurchaseOrderToSupplierMail;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    $this->team = $this->user->personalTeam();

    $this->supplier = Company::factory()->supplier()->for($this->team)->create([
        'email' => 'supplier@example.com',
    ]);
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $this->request = Request::factory()->for($this->team)->create();
});

function approvedOrder(Tests\TestCase $test): SupplierOrder
{
    return SupplierOrder::factory()
        ->for($test->team)
        ->recycle($test->request)
        ->recycle($test->supplier)
        ->recycle($test->currency)
        ->withStatus(OrderStatus::APPROVED)
        ->create();
}

it('sends the purchase order email and marks the order sent', function (): void {
    Mail::fake();
    $order = approvedOrder($this);

    $outcome = app(SendSupplierOrderToSupplier::class)->execute($order);

    expect($outcome)->toBe(SupplierOrderSendOutcome::Sent)
        ->and($order->refresh()->status)->toBe(OrderStatus::SENT)
        ->and($order->ordered_at)->not->toBeNull();

    Mail::assertSent(
        PurchaseOrderToSupplierMail::class,
        fn (PurchaseOrderToSupplierMail $mail): bool => $mail->hasTo('supplier@example.com'),
    );
});

it('marks the order sent without emailing when the supplier has no email', function (): void {
    Mail::fake();
    $this->supplier->update(['email' => null]);
    $order = approvedOrder($this);

    $outcome = app(SendSupplierOrderToSupplier::class)->execute($order);

    expect($outcome)->toBe(SupplierOrderSendOutcome::MarkedWithoutEmail)
        ->and($order->refresh()->status)->toBe(OrderStatus::SENT);

    Mail::assertNothingSent();
});

it('refuses to send an order that is not approved', function (): void {
    $order = SupplierOrder::factory()
        ->for($this->team)
        ->recycle($this->request)
        ->recycle($this->supplier)
        ->recycle($this->currency)
        ->withStatus(OrderStatus::CONFIRMED)
        ->create();

    expect(fn (): SupplierOrderSendOutcome => app(SendSupplierOrderToSupplier::class)->execute($order))
        ->toThrow(InvalidArgumentException::class);
});

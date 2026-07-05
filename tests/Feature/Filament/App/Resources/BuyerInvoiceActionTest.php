<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PNLStatus;
use App\Enums\QEStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Mail\Erp\InvoiceToBuyerMail;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\BuyerPayment;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    Currency::factory()->create([
        'code' => $this->team->getBaseCurrencyCode(),
        'is_active' => true,
    ]);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'email' => 'buyer@example.com',
        'credit_status' => true,
        'credit_limit' => '10000.00',
        'available_credit' => '10000.00',
        'credit_used' => '0.00',
    ]);
    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();

    // BuyerOrdersRelationManager::mount() (via HasRequestStageTab) gates access
    // behind an approved QE, an approved PNL, and an accepted buyer quote —
    // mirrors the setup in the sibling BuyerOrderActionTest.php.
    QuotationEvaluation::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => QEStatus::APPROVED]);

    ProfitAndLoss::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => PNLStatus::APPROVED]);

    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->accepted()
        ->create([
            'request_id' => $this->request,
            'buyer_id' => $this->buyer,
        ]);
});

function invoiceActionOrder(Tests\TestCase $test, OrderStatus $status): BuyerOrder
{
    $order = BuyerOrder::factory()
        ->recycle($test->team)
        ->recycle($test->user)
        ->forRequest($test->request)
        ->forBuyer($test->buyer)
        ->create(['status' => $status, 'confirmed_at' => now(), 'total' => '110.00', 'payment_terms_days' => 30]);

    BuyerOrderItem::factory()->recycle($test->team)->create([
        'buyer_order_id' => $order->getKey(),
        'description' => 'Item',
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_rate' => '10.0000',
        'is_tax_inclusive' => false,
        'sort_order' => 0,
    ]);

    return $order;
}

function invoiceActionRelationManager(Tests\TestCase $test): Testable
{
    return livewire(BuyerOrdersRelationManager::class, [
        'ownerRecord' => $test->request,
        'pageClass' => ViewRequest::class,
    ]);
}

it('issues an invoice and emails it to the buyer', function (): void {
    Mail::fake();

    $order = invoiceActionOrder($this, OrderStatus::CONFIRMED);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionVisible(TestAction::make('issueInvoice')->table($order))
        ->callAction(TestAction::make('issueInvoice')->table($order))
        ->assertNotified('Invoice issued');

    $invoice = BuyerInvoice::query()->where('buyer_order_id', $order->getKey())->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::SENT);

    Mail::assertSent(
        InvoiceToBuyerMail::class,
        fn (InvoiceToBuyerMail $mail): bool => $mail->invoice->is($invoice)
            && $mail->hasTo('buyer@example.com')
    );
});

it('hides issueInvoice for a draft order', function (): void {
    $order = invoiceActionOrder($this, OrderStatus::DRAFT);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionHidden(TestAction::make('issueInvoice')->table($order));
});

it('hides issueInvoice once an invoice already exists', function (): void {
    $order = invoiceActionOrder($this, OrderStatus::CONFIRMED);
    BuyerInvoice::issueFromOrder($order);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionHidden(TestAction::make('issueInvoice')->table($order->refresh()));
});

it('records a payment against the invoice and releases credit', function (): void {
    // Build the order in DRAFT and confirm() it, so credit is genuinely
    // reserved (a debit history row is written and hasReservedCredit() is true).
    $order = invoiceActionOrder($this, OrderStatus::DRAFT);
    $order->confirm(); // DRAFT -> CONFIRMED, reserves 110 (available 10000 -> 9890)
    $invoice = BuyerInvoice::issueFromOrder($order->refresh());

    $this->buyer->refresh();
    expect((float) $this->buyer->available_credit)->toBe(9890.00);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionVisible(TestAction::make('recordPayment')->table($order->refresh()))
        ->callAction(TestAction::make('recordPayment')->table($order->refresh()), [
            'amount' => '110.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            'payment_date' => now()->toDateString(),
        ])
        ->assertNotified('Payment recorded');

    $this->buyer->refresh();

    expect(BuyerPayment::query()->where('buyer_invoice_id', $invoice->getKey())->count())->toBe(1)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::PAID)
        ->and((float) $this->buyer->available_credit)->toBe(10000.00);
});

it('hides recordPayment when the order has no invoice', function (): void {
    $order = invoiceActionOrder($this, OrderStatus::CONFIRMED);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionHidden(TestAction::make('recordPayment')->table($order));
});

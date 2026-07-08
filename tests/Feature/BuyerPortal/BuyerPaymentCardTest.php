<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrepaymentType;
use App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\BuyerQuote;
use App\Models\BuyerQuotePaymentTerm;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use App\Services\Portal\BuyerPortalContext;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Notification::fake();
    config(['app.buyer_portal_enabled' => true]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();

    $this->portalUser = User::factory()->create([
        'email' => 'payment.card@buyer.test',
    ]);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->buyer->getKey(),
        'user_id' => $this->portalUser->getKey(),
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    $this->request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create();

    $this->quote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->admin)
        ->accepted()
        ->create([
            'request_id' => $this->request->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'prepayment_type' => PrepaymentType::PERCENT,
            'prepayment_percent' => 20,
        ]);

    BuyerQuotePaymentTerm::factory()->create([
        'buyer_quote_id' => $this->quote->getKey(),
        'due_days' => 30,
        'percentage' => 80,
        'sort_order' => 0,
    ]);

    $this->order = BuyerOrder::factory()
        ->recycle($this->team)
        ->recycle($this->admin)
        ->forRequest($this->request)
        ->forBuyer($this->buyer)
        ->fromQuote($this->quote)
        ->confirmed()
        ->create(['confirmed_at' => now(), 'total' => '1000']);

    $this->invoice = BuyerInvoice::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->forBuyerOrder($this->order)
        ->withCurrency($this->currency)
        ->sent()
        ->withTotals(900, 100, 1000)
        ->create(['net_days' => 30]);

    $this->actingAs($this->portalUser, 'buyer');
    Filament::setCurrentPanel('buyer');
    Filament::setTenant($this->team);
    app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());
});

it('renders the shared payment terms card with per-installment record buttons', function (): void {
    $base = $this->team->getBaseCurrency();
    expect($base)->not->toBeNull();

    livewire(ViewBuyerRequest::class, ['record' => $this->request->getRouteKey()])
        ->assertOk()
        ->assertSee('Prepayment')
        ->assertSee($base->format(200.0))   // 20% of 1000
        ->assertSee($base->format(800.0))   // 80% of 1000
        // Each grey button opens the shared modal for only its own installment.
        ->assertSee("mountAction('recordPayment', {amount: 200", false)
        ->assertSee("mountAction('recordPayment', {amount: 800", false);
});

it('records a buyer payment as pending without reducing the invoice balance', function (): void {
    livewire(ViewBuyerRequest::class, ['record' => $this->request->getRouteKey()])
        ->assertOk()
        ->callAction('recordPayment', [
            'amount' => '200',
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'TRX-CARD-1',
            'proof' => [UploadedFile::fake()->create('proof.pdf', 40, 'application/pdf')],
        ])
        ->assertHasNoActionErrors();

    $payment = BuyerPayment::query()->latest('id')->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->submitted_actor_type)->toBe('buyer')
        ->and($payment->submitted_by_id)->toBe($this->portalUser->getKey())
        ->and($payment->confirmed_by_id)->toBeNull()
        ->and($payment->confirmed_at)->toBeNull()
        ->and((float) $payment->amount)->toBe(200.0)
        ->and($payment->buyer_invoice_id)->toBe($this->invoice->getKey());

    // A pending payment must not touch the outstanding balance until staff confirm it.
    $this->invoice->refresh();
    expect((float) $this->invoice->amount_paid)->toBe(0.0)
        ->and($this->invoice->amount_outstanding)->toBe(1000.0);
});

it('requires proof of transfer for a buyer-submitted payment', function (): void {
    livewire(ViewBuyerRequest::class, ['record' => $this->request->getRouteKey()])
        ->assertOk()
        ->callAction('recordPayment', [
            'amount' => '200',
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            'payment_date' => now()->format('Y-m-d'),
        ])
        ->assertHasActionErrors(['proof' => 'required']);

    expect(BuyerPayment::query()->count())->toBe(0);
});

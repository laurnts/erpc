<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\PrepaymentType;
use App\Filament\Pages\Settings;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\BuyerQuote;
use App\Models\BuyerQuotePaymentTerm;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

    $this->quote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->user)
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
        ->recycle($this->user)
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

    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);
});

describe('payments card', function (): void {
    it('renders per-installment amounts and a paid / outstanding summary', function (): void {
        BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(200)
            ->create(['status' => PaymentStatus::Confirmed->value, 'submitted_actor_type' => 'staff']);

        $this->invoice->refresh();
        expect((float) $this->invoice->amount_paid)->toBe(200.0);

        $base = $this->team->getBaseCurrency();
        expect($base)->not->toBeNull();

        $prepayment = $base->format(200.0);   // 20% of 1000
        $termAmount = $base->format(800.0);    // 80% of 1000
        $outstanding = $base->format(800.0);   // 1000 total - 200 paid

        livewire(ViewRequest::class, ['record' => $this->request->getKey()])
            ->assertOk()
            ->assertSee('Prepayment')
            ->assertSee($prepayment)
            ->assertSee($termAmount)
            ->assertSee($outstanding)
            ->assertSee('Outstanding');
    });

    it('renders the Record Payment action in the card header while the invoice is open', function (): void {
        livewire(ViewRequest::class, ['record' => $this->request->getKey()])
            ->assertOk()
            ->assertSee('Record Payment');
    });
});

describe('payment bank settings', function (): void {
    it('saves the bank details into the team ERP settings', function (): void {
        livewire(Settings::class)
            ->fillForm([
                'payment_bank_name' => 'Acme Bank',
                'payment_account_holder' => 'Acme Trading Co',
                'payment_bank_account_number' => '1234567890',
                'payment_instructions' => 'Use the invoice number as the transfer reference.',
            ], 'paymentForm')
            ->call('savePaymentSettings')
            ->assertHasNoFormErrors()
            ->assertNotified('Bank Details Saved');

        $settings = $this->team->fresh()->getErpSettings();

        expect($settings->payment_bank_name)->toBe('Acme Bank')
            ->and($settings->payment_account_holder)->toBe('Acme Trading Co')
            ->and($settings->payment_bank_account_number)->toBe('1234567890')
            ->and($settings->payment_instructions)->toBe('Use the invoice number as the transfer reference.');
    });

    it('preserves bank details when other settings forms are saved', function (): void {
        livewire(Settings::class)
            ->fillForm([
                'payment_bank_name' => 'Persist Bank',
                'payment_account_holder' => 'Persist Holder',
                'payment_bank_account_number' => '9999',
                'payment_instructions' => 'keep me',
            ], 'paymentForm')
            ->call('savePaymentSettings')
            ->assertHasNoFormErrors()
            ->call('savePrefixSettings');

        $settings = $this->team->fresh()->getErpSettings();

        expect($settings->payment_bank_name)->toBe('Persist Bank')
            ->and($settings->payment_bank_account_number)->toBe('9999');
    });
});

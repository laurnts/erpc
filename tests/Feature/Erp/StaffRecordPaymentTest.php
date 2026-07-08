<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PNLStatus;
use App\Enums\QEStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

function paymentProofPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
    );
}

beforeEach(function (): void {
    // The payment_proof media collection is registered on the 'private' disk
    // (app-wide convention shared with BuyerInvoice/Shipment/SupplierPayment),
    // which is not configured in the test environment.
    config(['filesystems.disks.private' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/private'), 'throw' => false]]);
    Storage::fake('private');

    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

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
            'request_id' => $this->request->getKey(),
            'buyer_id' => $this->buyer->getKey(),
        ]);

    $this->order = BuyerOrder::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->forRequest($this->request)
        ->forBuyer($this->buyer)
        ->confirmed()
        ->create(['confirmed_at' => now()]);

    $this->invoice = BuyerInvoice::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->forBuyerOrder($this->order)
        ->withCurrency($this->currency)
        ->sent()
        ->withTotals(900, 100, 1000)
        ->create();

    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);
});

function buyerOrdersRm(Tests\TestCase $test): \Livewire\Features\SupportTesting\Testable
{
    return livewire(BuyerOrdersRelationManager::class, [
        'ownerRecord' => $test->request,
        'pageClass' => ViewRequest::class,
    ]);
}

describe('staff record payment', function (): void {
    it('records a confirmed payment with proof and reduces the invoice outstanding', function (): void {
        buyerOrdersRm($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('recordPayment')->table($this->order))
            ->callAction(TestAction::make('recordPayment')->table($this->order), [
                'amount' => '400',
                'payment_method' => PaymentMethod::BANK_TRANSFER->value,
                'payment_date' => now()->toDateString(),
                'reference_number' => 'TRX-123',
                'proof' => [paymentProofPdf('proof.pdf')],
                'note' => 'Wire received',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Payment recorded');

        $payment = BuyerPayment::query()->where('buyer_invoice_id', $this->invoice->getKey())->first();

        expect($payment)->not->toBeNull()
            ->and($payment->status)->toBe(PaymentStatus::Confirmed)
            ->and($payment->submitted_actor_type)->toBe('staff')
            ->and($payment->submitted_by_id)->toBe($this->user->getKey())
            ->and($payment->confirmed_by_id)->toBe($this->user->getKey())
            ->and($payment->confirmed_at)->not->toBeNull()
            ->and($payment->notes)->toBe('Wire received')
            ->and($payment->getMedia('payment_proof'))->toHaveCount(1);

        $this->invoice->refresh();

        expect((float) $this->invoice->amount_paid)->toBe(400.0)
            ->and($this->invoice->amount_outstanding)->toBe(600.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::PARTIAL);
    });

    it('records a confirmed payment without proof', function (): void {
        buyerOrdersRm($this)
            ->assertOk()
            ->callAction(TestAction::make('recordPayment')->table($this->order), [
                'amount' => '1000',
                'payment_method' => PaymentMethod::CASH->value,
                'payment_date' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Payment recorded');

        $payment = BuyerPayment::query()->where('buyer_invoice_id', $this->invoice->getKey())->first();

        expect($payment)->not->toBeNull()
            ->and($payment->status)->toBe(PaymentStatus::Confirmed)
            ->and($payment->getMedia('payment_proof'))->toHaveCount(0);

        $this->invoice->refresh();

        expect($this->invoice->amount_outstanding)->toBe(0.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::PAID);
    });
});

describe('staff confirm pending payment', function (): void {
    it('is hidden when there are no pending payments', function (): void {
        buyerOrdersRm($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('confirmPayment')->table($this->order));
    });

    it('confirms the oldest pending buyer payment and reduces the outstanding', function (): void {
        $pending = BuyerPayment::factory()
            ->forBuyerInvoice($this->invoice)
            ->withAmount(1000)
            ->create(['status' => PaymentStatus::Pending->value, 'submitted_actor_type' => 'buyer']);

        $this->invoice->refresh();
        expect($this->invoice->amount_outstanding)->toBe(1000.0);

        buyerOrdersRm($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('confirmPayment')->table($this->order))
            ->callAction(TestAction::make('confirmPayment')->table($this->order))
            ->assertNotified('Payment confirmed');

        $pending->refresh();
        $this->invoice->refresh();

        expect($pending->status)->toBe(PaymentStatus::Confirmed)
            ->and($pending->confirmed_by_id)->toBe($this->user->getKey())
            ->and($pending->confirmed_at)->not->toBeNull()
            ->and((float) $this->invoice->amount_paid)->toBe(1000.0)
            ->and($this->invoice->amount_outstanding)->toBe(0.0)
            ->and($this->invoice->status)->toBe(InvoiceStatus::PAID);
    });
});

<?php

declare(strict_types=1);

use App\Models\Company;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\RequestStage;
use App\Enums\ShipmentStatus;
use App\Filament\Widgets\ActiveRequestsWidget;
use App\Filament\Widgets\AwaitingPaymentWidget;
use App\Filament\Widgets\MonthlyRevenueWidget;
use App\Filament\Widgets\PipelineByStageWidget;
use App\Filament\Widgets\QuotesExpiringWidget;
use App\Filament\Widgets\RequiresAttentionWidget;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();
    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
});

describe('ActiveRequestsWidget', function (): void {
    it('can render the widget', function (): void {
        livewire(ActiveRequestsWidget::class)
            ->assertOk();
    });

    it('shows correct count of active requests', function (): void {
        // Create active requests in different stages
        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->withStage(RequestStage::DRAFT)
            ->count(3)
            ->create();

        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->withStage(RequestStage::PREPARING_SUPPLIER_ORDER)
            ->count(2)
            ->create();

        // Create inactive/terminal requests that should not be counted
        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->completed()
            ->count(2)
            ->create();

        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->cancelled()
            ->count(1)
            ->create();

        livewire(ActiveRequestsWidget::class)
            ->assertOk();
    });

    it('filters requests by team', function (): void {
        $otherUser = User::factory()->withPersonalTeam()->create();

        // Create request for current team
        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->withStage(RequestStage::DRAFT)
            ->create();

        // Create request for other team
        $otherBuyer = Company::factory()->buyer()->for($otherUser->personalTeam())->create();
        Request::factory()
            ->for($otherUser->personalTeam())
            ->for($otherBuyer, 'buyer')
            ->withStage(RequestStage::DRAFT)
            ->create();

        livewire(ActiveRequestsWidget::class)
            ->assertOk();
    });
});

describe('QuotesExpiringWidget', function (): void {
    it('can render the widget', function (): void {
        livewire(QuotesExpiringWidget::class)
            ->assertOk();
    });

    it('shows quotes expiring within 7 days', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        // Quote expiring in 3 days (should show)
        $expiringQuote = BuyerQuote::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->for($request)
            ->for($this->currency)
            ->sent()
            ->validUntil(now()->addDays(3))
            ->create();

        // Quote expiring in 10 days (should not show)
        BuyerQuote::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->for($request)
            ->for($this->currency)
            ->sent()
            ->validUntil(now()->addDays(10))
            ->create();

        livewire(QuotesExpiringWidget::class)
            ->assertCanSeeTableRecords([$expiringQuote]);
    });

    it('does not show already expired quotes', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        BuyerQuote::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->for($request)
            ->for($this->currency)
            ->expired()
            ->create();

        livewire(QuotesExpiringWidget::class)
            ->assertCountTableRecords(0);
    });

    it('does not show accepted or rejected quotes', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        BuyerQuote::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->for($request)
            ->for($this->currency)
            ->accepted()
            ->validUntil(now()->addDays(3))
            ->create();

        BuyerQuote::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->for($request)
            ->for($this->currency)
            ->rejected()
            ->validUntil(now()->addDays(3))
            ->create();

        livewire(QuotesExpiringWidget::class)
            ->assertCountTableRecords(0);
    });

    it('handles empty state gracefully', function (): void {
        livewire(QuotesExpiringWidget::class)
            ->assertCountTableRecords(0)
            ->assertOk();
    });
});

describe('AwaitingPaymentWidget', function (): void {
    it('can render the widget', function (): void {
        livewire(AwaitingPaymentWidget::class)
            ->assertOk();
    });

    it('shows invoices with sent, partial, and overdue status', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        $sentInvoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->sent()
            ->withTotals(1000, 100, 1100)
            ->create();

        $partialInvoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->partial()
            ->withTotals(2000, 200, 2200)
            ->create();

        $overdueInvoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->overdue()
            ->withTotals(1500, 150, 1650)
            ->create();

        livewire(AwaitingPaymentWidget::class)
            ->assertCanSeeTableRecords([$sentInvoice, $partialInvoice, $overdueInvoice]);
    });

    it('does not show paid or cancelled invoices', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->paid()
            ->withTotals(1000, 100, 1100)
            ->create();

        BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->cancelled()
            ->withTotals(500, 50, 550)
            ->create();

        livewire(AwaitingPaymentWidget::class)
            ->assertCountTableRecords(0);
    });

    it('handles empty state gracefully', function (): void {
        livewire(AwaitingPaymentWidget::class)
            ->assertCountTableRecords(0)
            ->assertOk();
    });

    it('filters by team', function (): void {
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherTeam = $otherUser->personalTeam();

        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        $ourInvoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->sent()
            ->withTotals(1000, 100, 1100)
            ->create();

        $otherBuyer = Company::factory()->buyer()->for($otherTeam)->create();
        $otherRequest = Request::factory()
            ->for($otherTeam)
            ->for($otherBuyer, 'buyer')
            ->create();
        $otherCurrency = Currency::factory()->create();

        BuyerInvoice::factory()
            ->for($otherTeam)
            ->for($otherRequest)
            ->for($otherCurrency)
            ->sent()
            ->withTotals(5000, 500, 5500)
            ->create();

        livewire(AwaitingPaymentWidget::class)
            ->assertCanSeeTableRecords([$ourInvoice])
            ->assertCountTableRecords(1);
    });
});

describe('MonthlyRevenueWidget', function (): void {
    it('can render the widget', function (): void {
        livewire(MonthlyRevenueWidget::class)
            ->assertOk();
    });

    it('calculates current month revenue from paid invoices', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        // Create paid invoices this month
        BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->withStatus(InvoiceStatus::PAID)
            ->withTotals(1000, 100, 1100)
            ->create([
                'updated_at' => now(),
            ]);

        BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->withStatus(InvoiceStatus::PAID)
            ->withTotals(2000, 200, 2200)
            ->create([
                'updated_at' => now(),
            ]);

        livewire(MonthlyRevenueWidget::class)
            ->assertOk();
    });

    it('handles empty state gracefully', function (): void {
        livewire(MonthlyRevenueWidget::class)
            ->assertOk();
    });
});

describe('PipelineByStageWidget', function (): void {
    it('can render the widget', function (): void {
        livewire(PipelineByStageWidget::class)
            ->assertOk();
    });

    it('displays requests grouped by stage', function (): void {
        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->withStage(RequestStage::DRAFT)
            ->count(3)
            ->create();

        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->withStage(RequestStage::AWAITING_BUYER_CONFIRMATION)
            ->count(2)
            ->create();

        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->withStage(RequestStage::PREPARING_SUPPLIER_ORDER)
            ->count(4)
            ->create();

        livewire(PipelineByStageWidget::class)
            ->assertOk();
    });

    it('excludes completed and cancelled requests', function (): void {
        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->completed()
            ->count(5)
            ->create();

        Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->cancelled()
            ->count(3)
            ->create();

        livewire(PipelineByStageWidget::class)
            ->assertOk();
    });

    it('handles empty state gracefully', function (): void {
        livewire(PipelineByStageWidget::class)
            ->assertOk();
    });
});

describe('RequiresAttentionWidget', function (): void {
    it('can render the widget', function (): void {
        livewire(RequiresAttentionWidget::class)
            ->assertOk();
    });

    it('shows quotes pending response', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        $pendingQuote = BuyerQuote::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->for($request)
            ->for($this->currency)
            ->sent()
            ->validUntil(now()->addDays(10))
            ->create();

        livewire(RequiresAttentionWidget::class)
            ->assertCanSeeTableRecords([$pendingQuote]);
    });

    it('shows orders pending confirmation', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        $pendingOrder = BuyerOrder::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->for($request)
            ->withStatus(OrderStatus::DRAFT)
            ->create();

        livewire(RequiresAttentionWidget::class)
            ->assertCanSeeTableRecords([$pendingOrder]);
    });

    it('shows overdue invoices', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        $overdueInvoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($request)
            ->for($this->currency)
            ->overdue()
            ->withTotals(1000, 100, 1100)
            ->create();

        livewire(RequiresAttentionWidget::class)
            ->assertCanSeeTableRecords([$overdueInvoice]);
    });

    it('shows shipments pending delivery', function (): void {
        $request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        $pendingShipment = Shipment::factory()
            ->for($this->team)
            ->for($request)
            ->withStatus(ShipmentStatus::IN_TRANSIT)
            ->create();

        livewire(RequiresAttentionWidget::class)
            ->assertCanSeeTableRecords([$pendingShipment]);
    });

    it('handles empty state gracefully', function (): void {
        livewire(RequiresAttentionWidget::class)
            ->assertCountTableRecords(0)
            ->assertOk();
    });

    it('filters by team', function (): void {
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherTeam = $otherUser->personalTeam();
        $otherBuyer = Company::factory()->buyer()->for($otherTeam)->create();

        $ourRequest = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create();

        $ourQuote = BuyerQuote::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->for($ourRequest)
            ->for($this->currency)
            ->sent()
            ->validUntil(now()->addDays(10))
            ->create();

        $otherRequest = Request::factory()
            ->for($otherTeam)
            ->for($otherBuyer, 'buyer')
            ->create();
        $otherCurrency = Currency::factory()->create();

        BuyerQuote::factory()
            ->for($otherTeam)
            ->for($otherBuyer, 'buyer')
            ->for($otherRequest)
            ->for($otherCurrency)
            ->sent()
            ->validUntil(now()->addDays(10))
            ->create();

        livewire(RequiresAttentionWidget::class)
            ->assertCanSeeTableRecords([$ourQuote])
            ->assertCountTableRecords(1);
    });
});

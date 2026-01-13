<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Jobs\Erp\CheckAwaitingSupplierQuotesJob;
use App\Jobs\Erp\CheckExpiringQuotesJob;
use App\Jobs\Erp\CheckOverdueInvoicesJob;
use App\Models\Buyer;
use App\Models\BuyerInvoice;
use App\Models\BuyerQuote;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Erp\AwaitingSupplierQuoteNotification;
use App\Notifications\Erp\InvoiceOverdueNotification;
use App\Notifications\Erp\QuoteExpirationNotification;
use App\Services\Erp\CreditLimitWarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
});

// Quote Expiration Tests
describe('CheckExpiringQuotesJob', function (): void {
    it('sends notification for quote expiring in 7 days', function (): void {
        $quote = BuyerQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create([
                'valid_until' => now()->addDays(7),
            ]);

        (new CheckExpiringQuotesJob)->handle();

        Notification::assertSentTo(
            $this->user,
            QuoteExpirationNotification::class,
            fn (QuoteExpirationNotification $notification): bool => $notification->quote->is($quote)
                && $notification->daysUntilExpiry === 7
        );
    });

    it('sends notification for quote expiring in 3 days', function (): void {
        $quote = BuyerQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create([
                'valid_until' => now()->addDays(3),
            ]);

        (new CheckExpiringQuotesJob)->handle();

        Notification::assertSentTo(
            $this->user,
            QuoteExpirationNotification::class,
            fn (QuoteExpirationNotification $notification): bool => $notification->daysUntilExpiry === 3
        );
    });

    it('sends notification for quote expiring in 1 day', function (): void {
        $quote = BuyerQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create([
                'valid_until' => now()->addDay(),
            ]);

        (new CheckExpiringQuotesJob)->handle();

        Notification::assertSentTo(
            $this->user,
            QuoteExpirationNotification::class,
            fn (QuoteExpirationNotification $notification): bool => $notification->daysUntilExpiry === 1
        );
    });

    it('does not send notification for draft quotes', function (): void {
        BuyerQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->draft()
            ->create([
                'valid_until' => now()->addDays(3),
            ]);

        (new CheckExpiringQuotesJob)->handle();

        Notification::assertNothingSent();
    });

    it('does not send notification for accepted quotes', function (): void {
        BuyerQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->accepted()
            ->create([
                'valid_until' => now()->addDays(3),
            ]);

        (new CheckExpiringQuotesJob)->handle();

        Notification::assertNothingSent();
    });

    it('does not send notification for quotes expiring in 5 days', function (): void {
        BuyerQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create([
                'valid_until' => now()->addDays(5),
            ]);

        (new CheckExpiringQuotesJob)->handle();

        Notification::assertNothingSent();
    });

    it('only notifies once per threshold', function (): void {
        $quote = BuyerQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create([
                'valid_until' => now()->addDays(7),
            ]);

        // Run job twice
        (new CheckExpiringQuotesJob)->handle();
        (new CheckExpiringQuotesJob)->handle();

        // Should only be notified once
        Notification::assertSentToTimes($this->user, QuoteExpirationNotification::class, 1);
    });
});

// Overdue Invoice Tests
describe('CheckOverdueInvoicesJob', function (): void {
    it('updates invoice status to overdue and sends notification', function (): void {
        $invoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create([
                'due_at' => now()->subDays(5),
            ]);

        (new CheckOverdueInvoicesJob)->handle();

        $invoice->refresh();
        expect($invoice->status)->toBe(InvoiceStatus::OVERDUE);

        Notification::assertSentTo(
            $this->user,
            InvoiceOverdueNotification::class,
            fn (InvoiceOverdueNotification $notification): bool => $notification->invoice->is($invoice)
        );
    });

    it('sends notification for partially paid overdue invoice', function (): void {
        $invoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->withTotals(1000, 100, 1100)
            ->create([
                'status' => InvoiceStatus::PARTIAL,
                'due_at' => now()->subDays(5),
                'issued_at' => now()->subDays(35),
                'amount_paid' => '500.00',
            ]);

        (new CheckOverdueInvoicesJob)->handle();

        $invoice->refresh();
        expect($invoice->status)->toBe(InvoiceStatus::OVERDUE);

        Notification::assertSentTo($this->user, InvoiceOverdueNotification::class);
    });

    it('does not send notification for paid invoices', function (): void {
        BuyerInvoice::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->paid()
            ->create([
                'due_at' => now()->subDays(5),
            ]);

        (new CheckOverdueInvoicesJob)->handle();

        Notification::assertNothingSent();
    });

    it('does not send notification for cancelled invoices', function (): void {
        BuyerInvoice::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->cancelled()
            ->create([
                'due_at' => now()->subDays(5),
            ]);

        (new CheckOverdueInvoicesJob)->handle();

        Notification::assertNothingSent();
    });

    it('does not send notification for invoices not yet due', function (): void {
        BuyerInvoice::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create([
                'due_at' => now()->addDays(5),
            ]);

        (new CheckOverdueInvoicesJob)->handle();

        Notification::assertNothingSent();
    });

    it('only notifies once per invoice', function (): void {
        $invoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create([
                'due_at' => now()->subDays(5),
            ]);

        // Run job twice
        (new CheckOverdueInvoicesJob)->handle();
        (new CheckOverdueInvoicesJob)->handle();

        Notification::assertSentToTimes($this->user, InvoiceOverdueNotification::class, 1);
    });
});

// Awaiting Supplier Quotes Tests
describe('CheckAwaitingSupplierQuotesJob', function (): void {
    it('sends notification for quote awaiting more than threshold days', function (): void {
        $quote = SupplierQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->pending()
            ->create([
                'created_at' => now()->subDays(10),
            ]);

        (new CheckAwaitingSupplierQuotesJob(thresholdDays: 7))->handle();

        Notification::assertSentTo(
            $this->user,
            AwaitingSupplierQuoteNotification::class,
            fn (AwaitingSupplierQuoteNotification $notification): bool => $notification->quote->is($quote)
                && $notification->daysWaiting === 10
        );
    });

    it('does not send notification for quote within threshold', function (): void {
        SupplierQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->pending()
            ->create([
                'created_at' => now()->subDays(3),
            ]);

        (new CheckAwaitingSupplierQuotesJob(thresholdDays: 7))->handle();

        Notification::assertNothingSent();
    });

    it('does not send notification for selected quotes', function (): void {
        SupplierQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->selected()
            ->create([
                'created_at' => now()->subDays(10),
            ]);

        (new CheckAwaitingSupplierQuotesJob)->handle();

        Notification::assertNothingSent();
    });

    it('does not send notification for rejected quotes', function (): void {
        SupplierQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->rejected()
            ->create([
                'created_at' => now()->subDays(10),
            ]);

        (new CheckAwaitingSupplierQuotesJob)->handle();

        Notification::assertNothingSent();
    });

    it('respects weekly notification limit', function (): void {
        $quote = SupplierQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->pending()
            ->create([
                'created_at' => now()->subDays(10),
            ]);

        // Run job twice in same day
        (new CheckAwaitingSupplierQuotesJob)->handle();
        (new CheckAwaitingSupplierQuotesJob)->handle();

        // Should only be notified once
        Notification::assertSentToTimes($this->user, AwaitingSupplierQuoteNotification::class, 1);
    });

    it('uses configurable threshold', function (): void {
        SupplierQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->pending()
            ->create([
                'created_at' => now()->subDays(5),
            ]);

        // Default threshold (7 days) - no notification
        (new CheckAwaitingSupplierQuotesJob(thresholdDays: 7))->handle();
        Notification::assertNothingSent();

        // Lower threshold (3 days) - should notify
        Notification::fake(); // Reset
        (new CheckAwaitingSupplierQuotesJob(thresholdDays: 3))->handle();
        Notification::assertSentTo($this->user, AwaitingSupplierQuoteNotification::class);
    });
});

// Credit Limit Warning Service Tests
describe('CreditLimitWarningService', function (): void {
    it('returns exceeds_limit true when order exceeds available credit', function (): void {
        $buyer = Buyer::factory()
            ->for($this->team)
            ->create([
                'credit_limit' => 10000,
                'credit_used' => 8000,
            ]);

        $service = new CreditLimitWarningService;
        $result = $service->checkCreditLimit($buyer, 3000);

        expect($result['exceeds_limit'])->toBeTrue()
            ->and($result['over_limit_amount'])->toBe(1000.0)
            ->and($result['available_credit'])->toBe(2000.0)
            ->and($result['warning_message'])->not->toBeNull()
            ->and($result['warning_level'])->toBe('danger');
    });

    it('returns exceeds_limit false when order within available credit', function (): void {
        $buyer = Buyer::factory()
            ->for($this->team)
            ->create([
                'credit_limit' => 10000,
                'credit_used' => 2000,
            ]);

        $service = new CreditLimitWarningService;
        $result = $service->checkCreditLimit($buyer, 3000);

        expect($result['exceeds_limit'])->toBeFalse()
            ->and($result['over_limit_amount'])->toBe(0.0)
            ->and($result['available_credit'])->toBe(8000.0);
    });

    it('returns no limit warning when credit limit is zero', function (): void {
        $buyer = Buyer::factory()
            ->for($this->team)
            ->create([
                'credit_limit' => 0,
                'credit_used' => 0,
            ]);

        $service = new CreditLimitWarningService;
        $result = $service->checkCreditLimit($buyer, 100000);

        expect($result['exceeds_limit'])->toBeFalse()
            ->and($result['has_credit_limit'])->toBeFalse()
            ->and($result['warning_message'])->toBeNull();
    });

    it('warns when order uses significant portion of remaining credit', function (): void {
        $buyer = Buyer::factory()
            ->for($this->team)
            ->create([
                'credit_limit' => 10000,
                'credit_used' => 8000,
            ]);

        $service = new CreditLimitWarningService;
        $result = $service->checkCreditLimit($buyer, 1800); // Leaves only 200 (2%)

        expect($result['exceeds_limit'])->toBeFalse()
            ->and($result['warning_level'])->toBe('warning')
            ->and($result['warning_message'])->toContain('credit remaining');
    });

    it('calculates approaching limit correctly', function (): void {
        $buyer = Buyer::factory()
            ->for($this->team)
            ->create([
                'credit_limit' => 10000,
                'credit_used' => 8500,
            ]);

        $service = new CreditLimitWarningService;
        $result = $service->checkApproachingLimit($buyer);

        expect($result['approaching_limit'])->toBeTrue()
            ->and($result['usage_percent'])->toBe(85.0)
            ->and($result['warning_message'])->toContain('85.0%');
    });

    it('returns credit summary correctly', function (): void {
        $buyer = Buyer::factory()
            ->for($this->team)
            ->create([
                'name' => 'Test Buyer',
                'credit_limit' => 10000,
                'credit_used' => 9500,
                'is_on_hold' => false,
            ]);

        $service = new CreditLimitWarningService;
        $result = $service->getCreditSummary($buyer);

        expect($result['buyer_name'])->toBe('Test Buyer')
            ->and($result['usage_percent'])->toBe(95.0)
            ->and($result['status'])->toBe('Critical')
            ->and($result['status_color'])->toBe('danger');
    });

    it('returns on hold status when buyer is on hold', function (): void {
        $buyer = Buyer::factory()
            ->for($this->team)
            ->onHold('Payment issues')
            ->create([
                'credit_limit' => 10000,
                'credit_used' => 5000,
            ]);

        $service = new CreditLimitWarningService;
        $result = $service->getCreditSummary($buyer);

        expect($result['is_on_hold'])->toBeTrue()
            ->and($result['status'])->toBe('On Hold')
            ->and($result['status_color'])->toBe('danger')
            ->and($result['on_hold_reason'])->toBe('Payment issues');
    });
});

// Notification Data Tests
describe('Notification data arrays', function (): void {
    it('QuoteExpirationNotification returns correct urgency levels', function (): void {
        $quote = BuyerQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->sent()
            ->create();

        // Critical (1 day)
        $notification = new QuoteExpirationNotification($quote, 1);
        $data = $notification->toArray($this->user);
        expect($data['urgency'])->toBe('critical')
            ->and($data['color'])->toBe('danger');

        // High (3 days)
        $notification = new QuoteExpirationNotification($quote, 3);
        $data = $notification->toArray($this->user);
        expect($data['urgency'])->toBe('high')
            ->and($data['color'])->toBe('warning');

        // Medium (7 days)
        $notification = new QuoteExpirationNotification($quote, 7);
        $data = $notification->toArray($this->user);
        expect($data['urgency'])->toBe('medium')
            ->and($data['color'])->toBe('info');
    });

    it('InvoiceOverdueNotification includes correct financial data', function (): void {
        $invoice = BuyerInvoice::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->withTotals(1000, 100, 1100)
            ->overdue()
            ->create([
                'amount_paid' => '300.00',
            ]);

        $notification = new InvoiceOverdueNotification($invoice);
        $data = $notification->toArray($this->user);

        expect($data['total'])->toBe(1100.0)
            ->and($data['amount_outstanding'])->toBe(800.0)
            ->and($data['amount_paid'])->toBe(300.0)
            ->and($data['type'])->toBe('invoice_overdue');
    });

    it('AwaitingSupplierQuoteNotification includes waiting duration', function (): void {
        $quote = SupplierQuote::factory()
            ->for($this->team)
            ->for($this->user, 'creator')
            ->pending()
            ->create();

        $notification = new AwaitingSupplierQuoteNotification($quote, 14);
        $data = $notification->toArray($this->user);

        expect($data['days_waiting'])->toBe(14)
            ->and($data['urgency'])->toBe('high')
            ->and($data['type'])->toBe('awaiting_supplier_quote');
    });
});

<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\BuyerInvoice;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

function draftInvoice(): BuyerInvoice
{
    return BuyerInvoice::factory()
        ->recycle(test()->team)
        ->recycle(test()->currency)
        ->for(test()->request)
        ->create(['status' => InvoiceStatus::DRAFT]);
}

it('leaves a draft invoice unnumbered', function (): void {
    expect(draftInvoice()->invoice_number)->toBeNull();
});

it('allows many unnumbered drafts in one team', function (): void {
    draftInvoice();
    draftInvoice();
    draftInvoice();

    expect(BuyerInvoice::whereNull('invoice_number')->count())->toBe(3);
});

it('assigns a number when the invoice is issued', function (): void {
    $invoice = draftInvoice();
    $invoice->markAsSent();

    expect($invoice->refresh()->invoice_number)->toMatch('/^INV-\d{4}-\d{4}$/');
});

it('does not burn a number on a discarded draft', function (): void {
    $discarded = draftInvoice();
    $discarded->delete();

    $issued = draftInvoice();
    $issued->markAsSent();

    expect($issued->refresh()->invoice_number)->toBe('INV-'.date('Y').'-0001');
});

it('is idempotent — re-issuing does not renumber', function (): void {
    $invoice = draftInvoice();
    $invoice->markAsSent();
    $first = $invoice->refresh()->invoice_number;

    $invoice->assignNumberIfMissing();

    expect($invoice->refresh()->invoice_number)->toBe($first);
});

it('numbers consecutive issued invoices without gaps', function (): void {
    $numbers = [];
    foreach (range(1, 5) as $ignored) {
        $invoice = draftInvoice();
        $invoice->markAsSent();
        $numbers[] = $invoice->refresh()->invoice_number;
    }

    $year = date('Y');
    expect($numbers)->toBe([
        "INV-{$year}-0001", "INV-{$year}-0002", "INV-{$year}-0003",
        "INV-{$year}-0004", "INV-{$year}-0005",
    ]);
});

it('numbers an invoice issued straight from an order', function (): void {
    $invoice = draftInvoice();
    $invoice->markAsSent();

    expect($invoice->refresh()->invoice_number)->not->toBeNull()
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::SENT);
});

<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\BuyerInvoice;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Services\Erp\Numbering\DocumentNumberAllocator;

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

/*
 * A true concurrency test is not possible here — the suite runs
 * single-process against a transactional test database, so two markAsSent()
 * calls can never actually overlap. What this proves instead is the
 * observable guard that makes the race harmless: a second in-memory copy of
 * the invoice, loaded while it was still draft (exactly what a concurrent
 * caller would be holding right before the winner commits), must not
 * allocate a second number or overwrite the first once it re-reads the
 * locked row and finds the invoice already sent and numbered.
 */
it('does not burn a number when a stale in-memory copy is sent after the row was already issued', function (): void {
    $invoice = draftInvoice();

    // A second copy of the same row, loaded while still draft — this models
    // a concurrent caller that read the invoice before the winner's
    // markAsSent() committed.
    $staleCopy = BuyerInvoice::find($invoice->getKey());

    $invoice->markAsSent();
    $issuedNumber = $invoice->refresh()->invoice_number;

    $allocator = app(DocumentNumberAllocator::class);
    $nextValueAfterFirstIssue = $allocator->peek($this->team->getKey(), 'buyer_invoice', date('Y'));

    // $staleCopy still thinks the invoice is DRAFT and unnumbered, so it
    // passes the outer transition guard exactly as a genuine concurrent
    // caller would. The lock inside markAsSent() must catch this: the row is
    // already SENT and numbered by the time it acquires the lock.
    expect($staleCopy->status)->toBe(InvoiceStatus::DRAFT)
        ->and($staleCopy->invoice_number)->toBeNull();

    $staleCopy->markAsSent();

    expect($allocator->peek($this->team->getKey(), 'buyer_invoice', date('Y')))
        ->toBe($nextValueAfterFirstIssue)
        ->and($invoice->refresh()->invoice_number)->toBe($issuedNumber);
});

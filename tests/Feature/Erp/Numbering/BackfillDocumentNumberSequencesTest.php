<?php

declare(strict_types=1);

use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\SupplierOrder;
use App\Models\Team;
use App\Services\Erp\Numbering\DocumentNumberAllocator;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->allocator = app(DocumentNumberAllocator::class);
});

it('seeds the counter above the highest existing number', function (): void {
    foreach (['BQ-2026-0007', 'BQ-2026-0042', 'BQ-2026-0009'] as $number) {
        BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->currency)
            ->for($this->request)
            ->create(['quote_number' => $number]);
    }

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(43);
});

it('is not fooled by lexical ordering past 9999', function (): void {
    foreach (['BQ-2026-9999', 'BQ-2026-10000'] as $number) {
        BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->currency)
            ->for($this->request)
            ->create(['quote_number' => $number]);
    }

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(10001);
});

it('counts soft-deleted documents so their numbers are never reissued', function (): void {
    $quote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0099']);
    $quote->delete();

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(100);
});

it('is idempotent and never lowers a counter', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0005']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();
    $this->allocator->next($this->team->getKey(), 'buyer_quote', '2026');
    $this->allocator->next($this->team->getKey(), 'buyer_quote', '2026');
    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(8);
});

it('changes nothing on a dry run', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0005']);

    $this->artisan('erp:backfill-document-sequences', ['--dry-run' => true])->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(1);
});

/**
 * The cutover migration invokes this command, so it runs at a fixed point in
 * migration history while SOURCES keeps evolving. A source table added to
 * SOURCES by a later migration does not exist yet when that point is replayed
 * on a fresh install — skipping it is what keeps `migrate` working there.
 */
it('skips a source table that does not exist yet', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0005']);

    Schema::drop('profit_and_losses');

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(6);
});

/**
 * quotation_evaluation, profit_and_loss and shipment_do capture the sequence
 * first and the period second — the reverse of every dashed format above.
 * SEQUENCE_FIRST in the command says which; getting a pair the wrong way
 * round would seed a counter from a year number instead of a sequence
 * number, a silent and severe data bug nothing else here would catch.
 */
it('reads a quotation_evaluation number with the sequence before the period', function (): void {
    // QuotationEvaluation::generateQeNumber() produces
    // sprintf('%03d-DS/QE/%s/%d', $sequence, $romanMonth, $year) — sequence
    // first, roman month, year. The period key is the year alone.
    QuotationEvaluation::factory()
        ->recycle($this->team)
        ->create(['qe_number' => '007-DS/QE/VII/2026']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'quotation_evaluation', '2026'))->toBe(8);
});

it('reads a profit_and_loss number with the sequence before the period', function (): void {
    // ProfitAndLoss::generatePnlNumber() produces
    // sprintf('%04d/EL-PNL/%s/%d', $sequence, $romanMonth, $year) — sequence
    // first. The period key is the year alone.
    ProfitAndLoss::factory()
        ->recycle($this->team)
        ->create(['pnl_number' => '0042/EL-PNL/VII/2026']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'profit_and_loss', '2026'))->toBe(43);
});

it('reads a shipment_do number with the sequence before the roman-month/year period', function (): void {
    // Shipment::generateDoNumber() produces
    // sprintf('%04d-CP/DO/%s/%d', $sequence, $romanMonth, $year) — sequence
    // first. Unlike the other reversed formats, this one is monthly: the
    // period key pairs the roman month with the year (e.g. "VII/2026").
    Shipment::factory()
        ->recycle($this->team)
        ->create(['do_number' => '0003-CP/DO/VII/2026']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'shipment_do', 'VII/2026'))->toBe(4);
});

it('reads the base sequence from a supplier_order PO number, ignoring the split suffix', function (): void {
    // SupplierOrderObserver appends a "-A"/"-B"/... suffix to a split PO's
    // base number. The backfill's pattern for supplier_order tolerates an
    // optional trailing "-[A-Z]" so a suffixed number still seeds the
    // counter from its base sequence rather than being skipped or misread.
    SupplierOrder::factory()
        ->recycle($this->team)
        ->create(['po_number' => 'PO-2026-0007-A']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'supplier_order', '2026'))->toBe(8);
});

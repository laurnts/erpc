<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

it('assigns a distinct quote number matching SQ-YYYY-NNNN to every created supplier quote', function (): void {
    // quote_number: null forces SupplierQuoteObserver::generateQuoteNumber() to
    // run; SupplierQuoteFactory's definition otherwise fills in its own faker
    // number, which would pass this assertion without exercising the
    // allocator at all.
    $numbers = collect(range(1, 25))
        ->map(fn (): string => (string) SupplierQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->supplier)
            ->recycle($this->currency)
            ->create(['quote_number' => null])
            ->quote_number)
        ->all();

    expect(array_unique($numbers))->toHaveCount(25);

    foreach ($numbers as $number) {
        expect($number)->toMatch('/^SQ-\d{4}-\d{4}$/');
    }
});

it('continues past the 9999 boundary', function (): void {
    // supplier_quotes carries a *global* unique index on quote_number (not
    // team-scoped like the other document tables) — this test stays on a
    // single team so it does not trip over that pre-existing schema gap.
    SupplierQuote::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->recycle($this->supplier)
        ->recycle($this->currency)
        ->create(['quote_number' => 'SQ-'.date('Y').'-9999']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    $next = SupplierQuote::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->recycle($this->supplier)
        ->recycle($this->currency)
        ->create(['quote_number' => null]);

    expect((string) $next->quote_number)->toBe('SQ-'.date('Y').'-10000');
});

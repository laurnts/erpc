<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
});

function makeRequestFor(Team $team, Company $buyer): Request
{
    return Request::factory()->recycle($team)->recycle($buyer)->create();
}

it('gives the second order on the same request a suffixed base number', function (): void {
    // The pre-existing suffix formula is chr(65 + $existingOrdersCount): when the
    // second order is created, one order already exists for the request, so the
    // suffix is chr(66) = 'B', not 'A' — the code never actually issues an 'A'
    // suffix. That is a separate, pre-existing bug in the untouched
    // $existingOrdersForRequest block (out of scope here; only the base-number
    // path below is being converted to the allocator), so this test pins the
    // real current behaviour rather than the ideal one.
    $request = makeRequestFor($this->team, $this->buyer);

    $first = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($request)->create();
    $second = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($request)->create();

    $base = (string) $first->po_number;

    expect((string) $second->po_number)->toBe($base.'-B');
});

it('gives a different request a new base number', function (): void {
    $requestA = makeRequestFor($this->team, $this->buyer);
    $requestB = makeRequestFor($this->team, $this->buyer);

    $a = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($requestA)->create();
    $b = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($requestB)->create();

    expect((string) $b->po_number)->not->toBe((string) $a->po_number);
});

it('continues base numbers past the 9999 boundary', function (): void {
    $seedRequest = makeRequestFor($this->team, $this->buyer);
    SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($seedRequest)
        ->create(['po_number' => 'PO-'.date('Y').'-9999']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    $next = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for(makeRequestFor($this->team, $this->buyer))
        ->create();

    expect((string) $next->po_number)->toBe('PO-'.date('Y').'-10000');
});

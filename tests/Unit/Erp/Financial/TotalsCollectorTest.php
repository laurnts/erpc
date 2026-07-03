<?php

declare(strict_types=1);

use App\Services\Erp\Financial\TotalsCollector;
use App\Services\Erp\Financial\TotalsLine;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->collector = new TotalsCollector;
});

it('aggregates pre-filtered lines into document totals with on-selling margin', function (): void {
    $lines = new Collection([
        new TotalsLine(lineSubtotal: 10000.0, lineTax: 1100.0, lineTotal: 11100.0, costPrice: 4000.0, quantity: 2.0),
        new TotalsLine(lineSubtotal: 5000.0, lineTax: 0.0, lineTotal: 5000.0, costPrice: 1000.0, quantity: 1.0),
    ]);

    $totals = $this->collector->collect($lines);

    expect($totals->subtotal)->toBe(15000.0)
        ->and($totals->taxTotal)->toBe(1100.0)
        ->and($totals->grandTotal)->toBe(16100.0)
        ->and($totals->costTotal)->toBe(9000.0)
        ->and($totals->marginAmount)->toBe(6000.0)
        ->and($totals->marginPercent)->toBe(40.0);
});

it('preserves the document identity subtotal + taxTotal === grandTotal', function (): void {
    $lines = new Collection([
        new TotalsLine(lineSubtotal: 901.0, lineTax: 99.0, lineTotal: 1000.0, costPrice: 500.0, quantity: 1.0),
        new TotalsLine(lineSubtotal: 901.0, lineTax: 99.0, lineTotal: 1000.0, costPrice: 500.0, quantity: 1.0),
    ]);

    $totals = $this->collector->collect($lines);

    expect($totals->subtotal + $totals->taxTotal)->toBe($totals->grandTotal);
});

it('returns all zeros for an empty collection', function (): void {
    $totals = $this->collector->collect(new Collection);

    expect($totals->subtotal)->toBe(0.0)
        ->and($totals->taxTotal)->toBe(0.0)
        ->and($totals->grandTotal)->toBe(0.0)
        ->and($totals->costTotal)->toBe(0.0)
        ->and($totals->marginAmount)->toBe(0.0)
        ->and($totals->marginPercent)->toBe(0.0);
});

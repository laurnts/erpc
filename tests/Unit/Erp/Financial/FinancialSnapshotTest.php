<?php

declare(strict_types=1);

use App\Data\Erp\FinancialSnapshot;
use Carbon\CarbonImmutable;

it('holds the frozen financial figures of an approved PNL', function (): void {
    $snapshot = new FinancialSnapshot(
        subtotal: 15000.0,
        taxTotal: 1100.0,
        grandTotal: 16100.0,
        costTotal: 9000.0,
        marginAmount: 6000.0,
        marginPercent: 40.0,
        currency: 'IDR',
        snapshotAt: CarbonImmutable::parse('2026-06-30 10:00:00'),
        buyerQuoteId: 42,
    );

    expect($snapshot->grandTotal)->toBe(16100.0)
        ->and($snapshot->marginPercent)->toBe(40.0)
        ->and($snapshot->currency)->toBe('IDR')
        ->and($snapshot->buyerQuoteId)->toBe(42);
});

it('round-trips through array form (as a JSON cast would)', function (): void {
    $snapshot = new FinancialSnapshot(
        subtotal: 15000.0,
        taxTotal: 1100.0,
        grandTotal: 16100.0,
        costTotal: 9000.0,
        marginAmount: 6000.0,
        marginPercent: 40.0,
        currency: 'IDR',
        snapshotAt: CarbonImmutable::parse('2026-06-30 10:00:00'),
        buyerQuoteId: 42,
    );

    $restored = FinancialSnapshot::from($snapshot->toArray());

    expect($restored->grandTotal)->toBe(16100.0)
        ->and($restored->costTotal)->toBe(9000.0)
        ->and($restored->buyerQuoteId)->toBe(42)
        ->and($restored->snapshotAt->toDateTimeString())->toBe('2026-06-30 10:00:00');
});

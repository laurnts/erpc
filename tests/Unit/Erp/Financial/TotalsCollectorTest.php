<?php

declare(strict_types=1);

use App\Services\Erp\Financial\TotalsCollector;
use App\Services\Erp\Financial\TotalsLine;
use App\Support\Money;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->collector = new TotalsCollector;
});

it('aggregates pre-filtered lines into document totals with on-selling margin', function (): void {
    $lines = new Collection([
        new TotalsLine(
            lineSubtotal: Money::fromDecimal('10000.0', 'IDR'),
            lineTax: Money::fromDecimal('1100.0', 'IDR'),
            lineTotal: Money::fromDecimal('11100.0', 'IDR'),
            costPrice: Money::fromDecimal('4000.0', 'IDR'),
            quantity: '2.0',
        ),
        new TotalsLine(
            lineSubtotal: Money::fromDecimal('5000.0', 'IDR'),
            lineTax: Money::fromDecimal('0.0', 'IDR'),
            lineTotal: Money::fromDecimal('5000.0', 'IDR'),
            costPrice: Money::fromDecimal('1000.0', 'IDR'),
            quantity: '1.0',
        ),
    ]);

    $totals = $this->collector->collect($lines, 'IDR');

    expect($totals->subtotal->toDecimal())->toBe('15000.0000')
        ->and($totals->taxTotal->toDecimal())->toBe('1100.0000')
        ->and($totals->grandTotal->toDecimal())->toBe('16100.0000')
        ->and($totals->costTotal->toDecimal())->toBe('9000.0000')
        ->and($totals->marginAmount->toDecimal())->toBe('6000.0000')
        ->and($totals->marginPercent)->toBe(40.0);
});

it('preserves the document identity subtotal + taxTotal === grandTotal', function (): void {
    $lines = new Collection([
        new TotalsLine(
            lineSubtotal: Money::fromDecimal('901.0', 'IDR'),
            lineTax: Money::fromDecimal('99.0', 'IDR'),
            lineTotal: Money::fromDecimal('1000.0', 'IDR'),
            costPrice: Money::fromDecimal('500.0', 'IDR'),
            quantity: '1.0',
        ),
        new TotalsLine(
            lineSubtotal: Money::fromDecimal('901.0', 'IDR'),
            lineTax: Money::fromDecimal('99.0', 'IDR'),
            lineTotal: Money::fromDecimal('1000.0', 'IDR'),
            costPrice: Money::fromDecimal('500.0', 'IDR'),
            quantity: '1.0',
        ),
    ]);

    $totals = $this->collector->collect($lines, 'IDR');

    expect($totals->subtotal->plus($totals->taxTotal)->compareTo($totals->grandTotal))->toBe(0);
});

it('returns all zeros for an empty collection', function (): void {
    $totals = $this->collector->collect(new Collection, 'IDR');

    expect($totals->subtotal->isZero())->toBeTrue()
        ->and($totals->taxTotal->isZero())->toBeTrue()
        ->and($totals->grandTotal->isZero())->toBeTrue()
        ->and($totals->costTotal->isZero())->toBeTrue()
        ->and($totals->marginAmount->isZero())->toBeTrue()
        ->and($totals->marginPercent)->toBe(0.0);
});

function idr(string $amount): Money
{
    return Money::fromDecimal($amount, 'IDR');
}

describe('exact document totals', function (): void {
    it('returns a zero total for no lines', function (): void {
        $totals = (new TotalsCollector)->collect(new Collection, 'IDR');

        expect($totals->subtotal->isZero())->toBeTrue()
            ->and($totals->grandTotal->isZero())->toBeTrue()
            ->and($totals->marginPercent)->toBe(0.0);
    });

    it('sums a hundred repeating-decimal lines exactly', function (): void {
        $lines = new Collection(array_fill(0, 100, new TotalsLine(
            lineSubtotal: idr('0.3333'),
            lineTax: idr('0.0367'),
            lineTotal: idr('0.3700'),
            costPrice: idr('0.2000'),
            quantity: '1',
        )));

        $totals = (new TotalsCollector)->collect($lines, 'IDR');

        expect($totals->subtotal->toDecimal())->toBe('33.3300')
            ->and($totals->taxTotal->toDecimal())->toBe('3.6700')
            ->and($totals->grandTotal->toDecimal())->toBe('37.0000')
            ->and($totals->costTotal->toDecimal())->toBe('20.0000');
    });

    it('reconciles subtotal plus tax to grand total', function (): void {
        $lines = new Collection([
            new TotalsLine(idr('100.1234'), idr('11.0136'), idr('111.1370'), idr('80'), '1'),
            new TotalsLine(idr('250.5678'), idr('27.5625'), idr('278.1303'), idr('200'), '1'),
        ]);

        $totals = (new TotalsCollector)->collect($lines, 'IDR');

        expect($totals->subtotal->plus($totals->taxTotal)->compareTo($totals->grandTotal))
            ->toBe(0);
    });

    it('reconciles margin to subtotal minus cost', function (): void {
        $lines = new Collection([
            new TotalsLine(idr('1000'), idr('110'), idr('1110'), idr('600'), '1'),
        ]);

        $totals = (new TotalsCollector)->collect($lines, 'IDR');

        expect($totals->marginAmount->compareTo($totals->subtotal->minus($totals->costTotal)))
            ->toBe(0)
            ->and($totals->marginAmount->toDecimal())->toBe('400.0000');
    });

    it('multiplies cost by quantity', function (): void {
        $lines = new Collection([
            new TotalsLine(idr('1000'), idr('0'), idr('1000'), idr('150'), '4'),
        ]);

        $totals = (new TotalsCollector)->collect($lines, 'IDR');

        expect($totals->costTotal->toDecimal())->toBe('600.0000');
    });
});

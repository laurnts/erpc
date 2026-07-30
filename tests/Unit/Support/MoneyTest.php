<?php

declare(strict_types=1);

use App\Support\Money;

it('constructs zero', function (): void {
    expect(Money::zero('IDR')->minorUnits)->toBe(0)
        ->and(Money::zero('IDR')->currency)->toBe('IDR');
});

it('round-trips a decimal string', function (): void {
    expect(Money::fromDecimal('1234.5678', 'IDR')->toDecimal())->toBe('1234.5678');
});

it('pads a decimal to scale 4', function (): void {
    expect(Money::fromDecimal('10', 'IDR')->toDecimal())->toBe('10.0000');
});

it('stores minor units at scale 4', function (): void {
    expect(Money::fromDecimal('1.2345', 'IDR')->minorUnits)->toBe(12345);
});

it('rounds half away from zero', function (): void {
    expect(Money::fromDecimal('0.00005', 'IDR')->minorUnits)->toBe(1)
        ->and(Money::fromDecimal('-0.00005', 'IDR')->minorUnits)->toBe(-1)
        ->and(Money::fromDecimal('0.00004', 'IDR')->minorUnits)->toBe(0);
});

it('adds and subtracts exactly', function (): void {
    $a = Money::fromDecimal('0.1', 'IDR');
    $b = Money::fromDecimal('0.2', 'IDR');

    expect($a->plus($b)->toDecimal())->toBe('0.3000')
        ->and($b->minus($a)->toDecimal())->toBe('0.1000');
});

it('survives the float trap', function (): void {
    // 0.1 + 0.2 === 0.30000000000000004 in binary floating point.
    $sum = Money::fromDecimal('0.1', 'IDR')->plus(Money::fromDecimal('0.2', 'IDR'));

    expect($sum->minorUnits)->toBe(3000)
        ->and($sum->compareTo(Money::fromDecimal('0.3', 'IDR')))->toBe(0);
});

it('sums a hundred thirds back to the exact total', function (): void {
    $total = Money::zero('IDR');
    foreach (range(1, 100) as $ignored) {
        $total = $total->plus(Money::fromDecimal('0.3333', 'IDR'));
    }

    expect($total->toDecimal())->toBe('33.3300');
});

it('multiplies with half-away-from-zero rounding', function (): void {
    expect(Money::fromDecimal('10.0000', 'IDR')->multipliedBy('3')->toDecimal())->toBe('30.0000')
        ->and(Money::fromDecimal('0.0001', 'IDR')->multipliedBy('0.5')->toDecimal())->toBe('0.0001')
        ->and(Money::fromDecimal('1.1111', 'IDR')->multipliedBy('2.5')->toDecimal())->toBe('2.7778');
});

it('divides with half-away-from-zero rounding', function (): void {
    expect(Money::fromDecimal('10.0000', 'IDR')->dividedBy('4')->toDecimal())->toBe('2.5000')
        ->and(Money::fromDecimal('1.0000', 'IDR')->dividedBy('3')->toDecimal())->toBe('0.3333');
});

it('rounds to whole units at scale 0', function (): void {
    expect(Money::fromDecimal('1234.5678', 'IDR')->roundedToScale(0)->toDecimal())->toBe('1235.0000')
        ->and(Money::fromDecimal('1234.4999', 'IDR')->roundedToScale(0)->toDecimal())->toBe('1234.0000')
        ->and(Money::fromDecimal('1234.5000', 'IDR')->roundedToScale(0)->toDecimal())->toBe('1235.0000')
        ->and(Money::fromDecimal('-1234.5000', 'IDR')->roundedToScale(0)->toDecimal())->toBe('-1235.0000');
});

it('rounds to two decimals', function (): void {
    expect(Money::fromDecimal('1.2345', 'IDR')->roundedToScale(2)->toDecimal())->toBe('1.2300')
        ->and(Money::fromDecimal('1.2350', 'IDR')->roundedToScale(2)->toDecimal())->toBe('1.2400');
});

it('leaves scale 4 untouched', function (): void {
    expect(Money::fromDecimal('1.2345', 'IDR')->roundedToScale(4)->toDecimal())->toBe('1.2345');
});

it('matches PHP round() at every scale it is used with', function (): void {
    foreach (['1234.5678', '0.5', '2.5', '-0.5', '19.995', '0.0001'] as $amount) {
        foreach ([0, 2, 4] as $scale) {
            expect(Money::fromDecimal($amount, 'IDR')->roundedToScale($scale)->toFloat())
                ->toBe(round((float) $amount, $scale), "amount={$amount} scale={$scale}");
        }
    }
});

it('crosses from a high-precision intermediate at the requested scale', function (): void {
    expect(Money::fromHighPrecision('90.09009009009009009009', 4, 'IDR')->toDecimal())->toBe('90.0901')
        ->and(Money::fromHighPrecision('90.09009009009009009009', 0, 'IDR')->toDecimal())->toBe('90.0000')
        ->and(Money::fromHighPrecision('9009.00900900900900900900', 4, 'IDR')->toDecimal())->toBe('9009.0090');
});

it('rounds an exact tie up, where the float pipeline rounds it down', function (): void {
    // 0.3333 * 2.5 is exactly 0.83325 — a tie, which rounds up to 0.8333.
    // In binary floating point the product computes to 0.83324999999999993516,
    // which is below the tie, so round() correctly rounds the wrong value down.
    // The defect is the multiplication, not the rounding.
    expect(round(0.3333 * 2.5, 4))->toBe(0.8332)
        ->and(bcmul('0.3333', '2.5', 20))->toBe('0.83325000000000000000')
        ->and(Money::fromHighPrecision(bcmul('0.3333', '2.5', 20), 4, 'IDR')->toDecimal())->toBe('0.8333');
});

it('rounds a high-precision string without constructing Money', function (): void {
    expect(Money::roundDecimal('1234.5678', 0))->toBe('1235')
        ->and(Money::roundDecimal('1234.4999', 0))->toBe('1234')
        ->and(Money::roundDecimal('-0.83325', 4))->toBe('-0.8333');
});

it('refuses to add different currencies', function (): void {
    expect(fn (): Money => Money::zero('IDR')->plus(Money::zero('USD')))
        ->toThrow(InvalidArgumentException::class);
});

it('compares', function (): void {
    $small = Money::fromDecimal('1', 'IDR');
    $large = Money::fromDecimal('2', 'IDR');

    expect($small->compareTo($large))->toBe(-1)
        ->and($large->compareTo($small))->toBe(1)
        ->and($small->compareTo($small))->toBe(0);
});

it('reports sign', function (): void {
    expect(Money::zero('IDR')->isZero())->toBeTrue()
        ->and(Money::fromDecimal('-0.0001', 'IDR')->isNegative())->toBeTrue()
        ->and(Money::fromDecimal('0.0001', 'IDR')->isNegative())->toBeFalse();
});

it('accepts a float input at the boundary without propagating its error', function (): void {
    expect(Money::fromDecimal(0.1 + 0.2, 'IDR')->toDecimal())->toBe('0.3000');
});

it('throws a domain error instead of a TypeError when addition overflows PHP_INT_MAX', function (): void {
    $huge = Money::ofMinorUnits(PHP_INT_MAX - 10, 'IDR');
    $more = Money::ofMinorUnits(100, 'IDR');

    expect(fn (): Money => $huge->plus($more))
        ->toThrow(InvalidArgumentException::class, 'overflows');
});

it('throws a domain error instead of a TypeError when subtraction overflows PHP_INT_MIN', function (): void {
    $hugeNegative = Money::ofMinorUnits(PHP_INT_MIN + 10, 'IDR');
    $more = Money::ofMinorUnits(100, 'IDR');

    expect(fn (): Money => $hugeNegative->minus($more))
        ->toThrow(InvalidArgumentException::class, 'overflows');
});

it('still sums a large-but-valid amount correctly', function (): void {
    $huge = Money::ofMinorUnits(PHP_INT_MAX - 100, 'IDR');
    $more = Money::ofMinorUnits(50, 'IDR');

    expect($huge->plus($more)->minorUnits)->toBe(PHP_INT_MAX - 50);
});

it('throws a domain error instead of silently clamping when multiplication overflows', function (): void {
    $huge = Money::ofMinorUnits(PHP_INT_MAX - 10, 'IDR');

    expect(fn (): Money => $huge->multipliedBy(2))
        ->toThrow(InvalidArgumentException::class, 'overflows');
});

it('still multiplies a large-but-valid amount correctly', function (): void {
    $huge = Money::ofMinorUnits(1_000_000_000, 'IDR');

    expect($huge->multipliedBy(2)->minorUnits)->toBe(2_000_000_000);
});

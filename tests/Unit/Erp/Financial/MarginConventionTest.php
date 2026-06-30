<?php

declare(strict_types=1);

use App\Services\Erp\Financial\MarginConvention;

describe('MarginConvention::marginPercent (on-selling)', function (): void {
    it('computes margin as (sellNet - cost) / sellNet * 100', function (): void {
        expect(MarginConvention::marginPercent(4600.0, 5000.0))->toBe(8.0);
    });

    it('returns 0.0 when sellNet is zero (guard)', function (): void {
        expect(MarginConvention::marginPercent(4600.0, 0.0))->toBe(0.0);
    });

    it('returns 0.0 when sellNet is negative (guard)', function (): void {
        expect(MarginConvention::marginPercent(4600.0, -100.0))->toBe(0.0);
    });

    it('yields a negative margin when cost exceeds sell', function (): void {
        expect(MarginConvention::marginPercent(5000.0, 4000.0))->toBe(-25.0);
    });
});

describe('MarginConvention::netUnitPrice', function (): void {
    it('derives selling price as cost / (1 - margin/100)', function (): void {
        expect(MarginConvention::netUnitPrice(4600.0, 8.0))->toBe(5000.0);
    });

    it('returns 0.0 at exactly 100% margin (guard)', function (): void {
        expect(MarginConvention::netUnitPrice(4600.0, 100.0))->toBe(0.0);
    });

    it('returns 0.0 above 100% margin (guard)', function (): void {
        expect(MarginConvention::netUnitPrice(4600.0, 150.0))->toBe(0.0);
    });

    it('round-trips with marginPercent', function (): void {
        $sell = MarginConvention::netUnitPrice(4600.0, 8.0);

        expect(MarginConvention::marginPercent(4600.0, $sell))->toBe(8.0);
    });
});

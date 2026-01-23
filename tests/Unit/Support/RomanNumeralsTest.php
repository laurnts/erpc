<?php

declare(strict_types=1);

use App\Support\RomanNumerals;

describe('month()', function (): void {
    it('converts all 12 months to correct Roman numerals', function (int $month, string $expected): void {
        expect(RomanNumerals::month($month))->toBe($expected);
    })->with([
        [1, 'I'],
        [2, 'II'],
        [3, 'III'],
        [4, 'IV'],
        [5, 'V'],
        [6, 'VI'],
        [7, 'VII'],
        [8, 'VIII'],
        [9, 'IX'],
        [10, 'X'],
        [11, 'XI'],
        [12, 'XII'],
    ]);

    it('throws InvalidArgumentException for month less than 1', function (): void {
        RomanNumerals::month(0);
    })->throws(InvalidArgumentException::class, 'Month must be between 1 and 12, 0 given.');

    it('throws InvalidArgumentException for month greater than 12', function (): void {
        RomanNumerals::month(13);
    })->throws(InvalidArgumentException::class, 'Month must be between 1 and 12, 13 given.');

    it('throws InvalidArgumentException for negative month', function (): void {
        RomanNumerals::month(-5);
    })->throws(InvalidArgumentException::class, 'Month must be between 1 and 12, -5 given.');
});

describe('number()', function (): void {
    it('converts numbers to correct Roman numerals', function (int $number, string $expected): void {
        expect(RomanNumerals::number($number))->toBe($expected);
    })->with([
        [1, 'I'],
        [4, 'IV'],
        [5, 'V'],
        [9, 'IX'],
        [10, 'X'],
        [40, 'XL'],
        [50, 'L'],
        [90, 'XC'],
        [100, 'C'],
        [400, 'CD'],
        [500, 'D'],
        [900, 'CM'],
        [1000, 'M'],
        [2024, 'MMXXIV'],
        [3999, 'MMMCMXCIX'],
    ]);

    it('throws InvalidArgumentException for number less than 1', function (): void {
        RomanNumerals::number(0);
    })->throws(InvalidArgumentException::class, 'Number must be positive, 0 given.');

    it('throws InvalidArgumentException for negative number', function (): void {
        RomanNumerals::number(-10);
    })->throws(InvalidArgumentException::class, 'Number must be positive, -10 given.');

    it('throws InvalidArgumentException for number greater than 3999', function (): void {
        RomanNumerals::number(4000);
    })->throws(InvalidArgumentException::class, 'Number must be 3999 or less, 4000 given.');

    it('throws InvalidArgumentException for large number', function (): void {
        RomanNumerals::number(10000);
    })->throws(InvalidArgumentException::class, 'Number must be 3999 or less, 10000 given.');
});

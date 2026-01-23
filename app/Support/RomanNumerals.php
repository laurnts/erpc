<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Utility class for converting numbers to Roman numerals.
 */
final readonly class RomanNumerals
{
    /**
     * Roman numeral mappings for months (1-12).
     *
     * @var array<int, string>
     */
    private const array MONTHS = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X',
        11 => 'XI',
        12 => 'XII',
    ];

    /**
     * Roman numeral mappings for general conversion.
     *
     * @var array<int, string>
     */
    private const array NUMERALS = [
        1000 => 'M',
        900 => 'CM',
        500 => 'D',
        400 => 'CD',
        100 => 'C',
        90 => 'XC',
        50 => 'L',
        40 => 'XL',
        10 => 'X',
        9 => 'IX',
        5 => 'V',
        4 => 'IV',
        1 => 'I',
    ];

    /**
     * Convert a month number (1-12) to Roman numeral.
     *
     * @throws InvalidArgumentException If month is not between 1 and 12
     */
    public static function month(int $month): string
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(
                sprintf('Month must be between 1 and 12, %d given.', $month)
            );
        }

        return self::MONTHS[$month];
    }

    /**
     * Convert any positive integer to Roman numeral.
     *
     * @throws InvalidArgumentException If number is not positive
     */
    public static function number(int $number): string
    {
        if ($number < 1) {
            throw new InvalidArgumentException(
                sprintf('Number must be positive, %d given.', $number)
            );
        }

        if ($number > 3999) {
            throw new InvalidArgumentException(
                sprintf('Number must be 3999 or less, %d given.', $number)
            );
        }

        $result = '';

        foreach (self::NUMERALS as $value => $numeral) {
            while ($number >= $value) {
                $result .= $numeral;
                $number -= $value;
            }
        }

        return $result;
    }
}

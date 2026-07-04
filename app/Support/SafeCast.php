<?php

declare(strict_types=1);

namespace App\Support;

use Stringable;

/**
 * Utility class for validated casting of mixed values, typically sourced
 * from JSON columns or Filament form state, to a specific scalar type.
 */
final readonly class SafeCast
{
    /**
     * Cast a value to float. Numeric strings, ints, and floats pass through;
     * anything else (including null, empty string, and non-numeric values)
     * returns the default.
     */
    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && $value !== '' && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * Cast a value to int. Numeric strings, ints, and floats pass through;
     * anything else (including null, empty string, and non-numeric values)
     * returns the default.
     */
    public static function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && $value !== '' && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Cast a value to string. Strings, ints, floats, and Stringable objects
     * pass through; null, arrays, and booleans return the default, since
     * PHP's implicit bool-to-string conversion ('1'/'') is surprising.
     */
    public static function toString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Cast a value to bool. Real booleans pass through, along with the
     * common truthy/falsy representations 1, 0, '1', '0', 'true', and
     * 'false'. Anything else returns the default.
     */
    public static function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return $default;
    }

    /**
     * Cast a value to array. Arrays pass through; anything else returns
     * the default.
     *
     * @param  array<array-key, mixed>  $default
     * @return array<array-key, mixed>
     */
    public static function toArray(mixed $value, array $default = []): array
    {
        if (is_array($value)) {
            return $value;
        }

        return $default;
    }
}

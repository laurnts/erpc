<?php

declare(strict_types=1);

namespace App\Support;

use DivisionByZeroError;
use InvalidArgumentException;

/**
 * An exact monetary amount, held as integer minor units at scale 4 to match the
 * decimal(18,4) money columns.
 *
 * Floats are not used for arithmetic anywhere in this class. A float may be
 * accepted at the boundary (fromDecimal, multipliedBy) because callers still
 * read floats out of Eloquent casts, but it is immediately normalised to a
 * decimal string and never participates in an operation.
 *
 * SCALE is the storage scale, not the presentation scale. Documents round their
 * persisted line amounts to a per-family scale (0 for buyer quotes, 4 for
 * supplier quotes) via roundedToScale(); that rounding is a business rule and is
 * applied by the caller, never assumed here.
 *
 * Rounding is half-away-from-zero, matching PHP's round().
 *
 * Currency mismatches throw InvalidArgumentException directly rather than a
 * dedicated exception type: this app has no App\Exceptions namespace and no
 * established exemption for a class extending a framework exception under
 * App\Support (see ArchTest.php's "avoid inheritance" rule), and a prior
 * attempt at a similar single-purpose exception (SequenceContendedException)
 * was removed in favour of an existing exception type for exactly that reason.
 * Converting between currencies is an explicit business operation with an
 * exchange rate and a rate date; it never happens implicitly inside an
 * arithmetic operator.
 */
final readonly class Money
{
    public const int SCALE = 4;

    /**
     * Scale for intermediate values held outside Money, by callers doing chained
     * arithmetic. See fromHighPrecision().
     */
    public const int PRECISION = 20;

    private const string FACTOR = '10000';

    /**
     * Intermediate bcmath scale for a single operation. Two guard digits beyond
     * SCALE so the rounding decision is made on a value that has not already
     * been truncated.
     */
    private const int GUARD_SCALE = 6;

    private function __construct(
        public int $minorUnits,
        public string $currency,
    ) {}

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public static function ofMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, $currency);
    }

    /**
     * Build from a decimal amount. Accepts the string form Eloquent's decimal
     * cast produces, an int, or a float — the float is stringified with enough
     * precision to survive the trip and then rounded once.
     */
    public static function fromDecimal(string|int|float $amount, string $currency): self
    {
        $scaled = bcmul(self::normalise($amount), self::FACTOR, self::GUARD_SCALE);

        return new self((int) self::roundHalfAwayFromZero($scaled), $currency);
    }

    /**
     * Money columns are decimal(18,4), so a single stored amount can carry
     * minor units up to roughly PHP_INT_MAX already. Summing enough large
     * lines can still push the result past PHP_INT_MAX: native int addition
     * then silently promotes to float, and the private constructor's `int`
     * parameter throws a bare TypeError for it — a poor failure mode for
     * what is really a domain-sized amount. bcadd is used here instead of
     * native `+` so the overflow can be detected and reported clearly before
     * any value is constructed.
     */
    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        $sum = bcadd((string) $this->minorUnits, (string) $other->minorUnits, 0);

        return new self(
            $this->assertFitsInInt($sum, sprintf(
                'Adding %s and %s %s',
                $this->toDecimal(),
                $other->toDecimal(),
                $this->currency,
            )),
            $this->currency,
        );
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        $difference = bcsub((string) $this->minorUnits, (string) $other->minorUnits, 0);

        return new self(
            $this->assertFitsInInt($difference, sprintf(
                'Subtracting %s from %s %s',
                $other->toDecimal(),
                $this->toDecimal(),
                $this->currency,
            )),
            $this->currency,
        );
    }

    public function multipliedBy(string|int|float $factor): self
    {
        $normalisedFactor = self::normalise($factor);
        $product = bcmul((string) $this->minorUnits, $normalisedFactor, self::GUARD_SCALE);
        $rounded = self::roundHalfAwayFromZero($product);

        return new self(
            $this->assertFitsInInt($rounded, sprintf(
                'Multiplying %s %s by %s',
                $this->toDecimal(),
                $this->currency,
                $normalisedFactor,
            )),
            $this->currency,
        );
    }

    public function dividedBy(string|int|float $divisor): self
    {
        $normalised = self::normalise($divisor);

        if (bccomp($normalised, '0', self::GUARD_SCALE) === 0) {
            throw new DivisionByZeroError('Cannot divide a monetary amount by zero.');
        }

        $quotient = bcdiv((string) $this->minorUnits, $normalised, self::GUARD_SCALE);
        $rounded = self::roundHalfAwayFromZero($quotient);

        return new self(
            $this->assertFitsInInt($rounded, sprintf(
                'Dividing %s %s by %s',
                $this->toDecimal(),
                $this->currency,
                $normalised,
            )),
            $this->currency,
        );
    }

    /**
     * Round to a presentation scale, keeping the result in scale-4 minor units.
     * roundedToScale(0) on 1234.5678 yields 1235.0000, i.e. 12350000 minor units.
     *
     * A scale at or beyond SCALE is a no-op: there is no precision left to drop.
     */
    public function roundedToScale(int $scale): self
    {
        if ($scale >= self::SCALE) {
            return $this;
        }

        $step = 10 ** (self::SCALE - $scale);
        $quotient = bcdiv((string) $this->minorUnits, (string) $step, self::GUARD_SCALE);
        $rounded = (int) self::roundHalfAwayFromZero($quotient);

        return new self($rounded * $step, $this->currency);
    }

    /**
     * Cross from a high-precision intermediate into an exact rounded amount.
     *
     * This is the rounding boundary. A caller doing chained arithmetic keeps its
     * intermediates as PRECISION-scale decimal strings and calls this once, at
     * the end — rounding at every step instead was measured to diverge from the
     * previous float implementation in 22% of cases, because rounding a per-unit
     * figure before multiplying by quantity is not the same as rounding after.
     *
     * @param  numeric-string  $decimal
     */
    public static function fromHighPrecision(string $decimal, int $roundingScale, string $currency): self
    {
        return self::fromDecimal(self::roundDecimal($decimal, $roundingScale), $currency);
    }

    /**
     * Round a decimal string half-away-from-zero at an arbitrary scale, without
     * going through Money. Exposed so callers can round intermediates in place.
     *
     * @param  numeric-string  $value
     */
    public static function roundDecimal(string $value, int $scale): string
    {
        $adjustment = bcdiv('5', bcpow('10', (string) ($scale + 1), 0), $scale + 1);

        if (bccomp($value, '0', self::PRECISION) < 0) {
            $adjustment = bcmul($adjustment, '-1', $scale + 1);
        }

        return bcadd($value, $adjustment, $scale);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits <=> $other->minorUnits;
    }

    /**
     * The persistence form: a fixed-scale decimal string, safe to hand to a
     * decimal(18,4) column without passing through a float.
     */
    public function toDecimal(): string
    {
        return bcdiv((string) $this->minorUnits, self::FACTOR, self::SCALE);
    }

    /**
     * Presentation only. Never feed this back into arithmetic — that is the
     * defect this class exists to remove.
     */
    public function toFloat(): float
    {
        return (float) $this->toDecimal();
    }

    /**
     * @return numeric-string
     */
    private static function normalise(string|int|float $value): string
    {
        $normalised = is_float($value)
            ? number_format($value, self::GUARD_SCALE, '.', '')
            : (string) $value;

        if (! is_numeric($normalised)) {
            throw new InvalidArgumentException("Money amount [{$normalised}] is not numeric.");
        }

        return $normalised;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf('Cannot combine %s and %s without an explicit conversion.', $this->currency, $other->currency)
            );
        }
    }

    /**
     * Guards the boundary where an arbitrary-precision bcmath result is
     * narrowed into the int this class stores. Casting an out-of-range
     * numeric string to int does not throw — PHP silently clamps it to
     * PHP_INT_MAX/PHP_INT_MIN — so the range must be checked before the cast,
     * not inferred from its result.
     *
     * @param  string  $integerValue  An exact integer-valued decimal string (scale 0), as bcmath returns it.
     */
    private function assertFitsInInt(string $integerValue, string $operationDescription): int
    {
        // bcadd/bcsub/bcmul/bcdiv are all typed to return plain `string`, not
        // `numeric-string`, even though every value this method is called with
        // is always numeric — this narrows the type for bccomp() below rather
        // than suppressing the check.
        if (! is_numeric($integerValue)) {
            throw new InvalidArgumentException("Money arithmetic produced a non-numeric intermediate value [{$integerValue}].");
        }

        if (bccomp($integerValue, (string) PHP_INT_MAX, 0) > 0 || bccomp($integerValue, (string) PHP_INT_MIN, 0) < 0) {
            throw new InvalidArgumentException(sprintf(
                '%s overflows: the result (%s minor units) exceeds the range a Money amount can represent (%s to %s minor units).',
                $operationDescription,
                $integerValue,
                (string) PHP_INT_MIN,
                (string) PHP_INT_MAX,
            ));
        }

        return (int) $integerValue;
    }

    /**
     * bcadd at scale 0 truncates toward zero, so adding ±0.5 first yields
     * half-away-from-zero rounding — the same behaviour as PHP's round().
     *
     * @param  numeric-string  $value
     */
    private static function roundHalfAwayFromZero(string $value): string
    {
        $adjustment = bccomp($value, '0', self::GUARD_SCALE) >= 0 ? '0.5' : '-0.5';

        return bcadd($value, $adjustment, 0);
    }
}

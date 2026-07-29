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

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function multipliedBy(string|int|float $factor): self
    {
        $product = bcmul((string) $this->minorUnits, self::normalise($factor), self::GUARD_SCALE);

        return new self((int) self::roundHalfAwayFromZero($product), $this->currency);
    }

    public function dividedBy(string|int|float $divisor): self
    {
        $normalised = self::normalise($divisor);

        if (bccomp($normalised, '0', self::GUARD_SCALE) === 0) {
            throw new DivisionByZeroError('Cannot divide a monetary amount by zero.');
        }

        $quotient = bcdiv((string) $this->minorUnits, $normalised, self::GUARD_SCALE);

        return new self((int) self::roundHalfAwayFromZero($quotient), $this->currency);
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

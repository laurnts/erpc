# Exact Money Arithmetic Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace PHP float arithmetic in the line and totals engine with an exact integer-minor-unit `Money` value object, so deal margin — the number this business sells — cannot accumulate binary rounding error, while preserving every currently-visible rounded figure exactly.

**Architecture:** Storage is already exact: every money column is PostgreSQL
`decimal(18,4)`, which is arbitrary-precision. The defect is entirely in PHP, where
`LineCalculator` and `TotalsCollector` take `float`, divide by `(1 + $taxRate / 100)`,
and sum with `+`. **No schema migration is needed and none is in this plan.** A `Money`
value object holding integer minor units at scale 4 (matching the columns) becomes the
currency of the financial services; conversion happens at the model boundary.

Margin *percentages* stay float — a percentage is a ratio, not an amount, and
`MarginConvention` is the right place for it to remain one.

This is §5 of `/Users/laurnts/Sites/pos/ARCHITECTURE.md` — *money as integers (minor
units) plus a currency code, never floats, with the invariant suite enforcing
reconciliation* — applied to the one part of erpc where it actually pays.

**Tech Stack:** PHP 8.4 with `ext-bcmath` (already installed, `Dockerfile:54`),
Laravel 12, Pest 4.

## Global Constraints

- All PHP files declare `declare(strict_types=1);`
- All classes `final` by default; value objects `final readonly`
- Comparisons use `===` / `!==` exclusively
- Tooling runs through the Docker wrapper: `php vendor/bin/<tool>`
- Before finalizing any change: `php vendor/bin/rector process <changed files>` then `php vendor/bin/pint --dirty`
- **`Money` carries scale 4 internally**, matching `decimal(18,4)`. **Presentation
  rounding scale is a separate, per-call value and must be preserved exactly as it is
  today** — see below. Do not collapse the two.
- **Output changes are bounded and measured, not zero.** See *Measured impact* below.
  Every existing financial test is expected to pass unmodified; a small number may not,
  and each failure must be shown to be a one-unit-in-the-last-place tie before its
  expectation is edited.

## Measured impact (validated 2026-07-29, not estimated)

The design below was built and run against the current float implementation over a
16,800-field grid (10 prices × 6 tax rates × 7 quantities × 2 scales × 2 price bases ×
taxable on/off), on PHP 8.4 with bcmath in the project's own container:

| Path | Fields | Differ from today | Largest difference |
|---|---|---|---|
| Buyer quotes (`roundingScale: 0`) | 8 400 | **2 (0.02%)** | 1 rupiah |
| Supplier quotes (`roundingScale: 4`) | 8 400 | **57 (0.68%)** | 0.0001 |

Every difference is exactly one unit in the last place; **no case differs by more.**
Invariant I-M1 (`lineSubtotal + lineTax === lineTotal`) held in all 16 800 fields.

All divergences are the same shape, and the mechanism was verified rather than assumed.
`0.3333 × 2.5` is exactly `0.83325` — a tie, which rounds up to `0.8333`. In binary
floating point the *product* computes to `0.83324999999999993516`, which sits below the
tie, so `round()` faithfully rounds the value it was handed *down* to `0.8332`.

`round()` is not the culprit — given the same number it agrees with this implementation
in all 27 amount/scale combinations tested. The culprit is that float multiplication
moved the product to the wrong side of the boundary before rounding ever ran. That is
also why fixing this by "rounding more carefully" would not have worked.

**In every differing case the new value is the arithmetically correct one and the old
value is a float artefact.**

Two consequences worth stating plainly to the business before this ships:

1. Stored values do not move on deploy. Line amounts are recalculated on save, so a
   historical document only changes if it is edited.
2. On recalculation, roughly one buyer-quote line in five thousand will change by one
   rupiah, always upward, always because the old value was rounded the wrong way.

### A rejected design, recorded so it is not retried

The first draft of this plan used `Money` for the *intermediate* values inside
`LineCalculator` — `$unitPrice->dividedBy($divisor)` then `->multipliedBy($quantity)`.
Validation rejected it: **583 of 2 592 cases diverged (22%), some by 0.001 or more.**
The cause is that `Money` rounds to scale 4 on every operation, so the per-unit figure
was rounded before being multiplied out, while the float version carried ~15 significant
digits into the multiply. Intermediates must stay at high precision; `Money` is the
rounding boundary, not the arithmetic carrier. That is why `Money::fromHighPrecision()`
exists in Task 1 and why `LineCalculator` computes in decimal strings.

## The rounding scale is load-bearing — do not normalise it

`LineCalculator::calculate()` takes an `int $currencyDecimals` argument, and the two
call sites pass **different values**:

| Call site | `currencyDecimals` | Effect |
|---|---|---|
| `app/Models/BuyerQuoteItem.php:295` | `0` | Buyer quote lines round to whole units (IDR has no minor unit in practice) |
| `app/Models/SupplierQuoteItem.php:281` | `4` | Supplier quote lines keep four decimals |

`BuyerQuoteItem::recalculatePrices()` additionally does `round($marginAmount, 0)`.

So the system deliberately rounds buyer-facing amounts to whole rupiah and supplier-side
amounts to four decimals. Forcing a single scale would re-round every buyer quote line
and change figures customers have already been quoted. **This plan keeps that parameter**
— renamed `roundingScale` for clarity — and adds `Money::roundedToScale()` to apply it
exactly. Internal arithmetic stays at scale 4; only the results that get persisted are
rounded, exactly where they are rounded today.

## Blast radius

`LineCalculator` — 4 call sites:
- `app/Models/BuyerQuoteItem.php:295` (`recalculatePrices`)
- `app/Models/SupplierQuoteItem.php:281` (`calculateTotals`)
- `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php:2705`
- `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php:2752`

`TotalsCollector` — 3 call sites:
- `app/Models/BuyerQuoteItem.php:492` (`collectTotals`)
- `app/Models/BuyerQuote.php:602`
- `app/Models/SupplierQuote.php:339`

Seven call sites, three value objects, two services. That is the whole surface.

---

### Task 1: The Money value object

**Files:**
- Create: `app/Support/Money.php`
- Create: `app/Support/CurrencyMismatch.php`
- Modify: `composer.json` — declare the bcmath dependency
- Test: `tests/Unit/Support/MoneyTest.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  ```php
  final readonly class Money
  {
      public const int SCALE = 4;
      public const int PRECISION = 20;   // intermediate scale for callers doing chained arithmetic

      public static function zero(string $currency): self;
      public static function ofMinorUnits(int $minorUnits, string $currency): self;
      public static function fromDecimal(string|int|float $amount, string $currency): self;
      public static function fromHighPrecision(string $decimal, int $roundingScale, string $currency): self;
      public static function roundDecimal(string $value, int $scale): string;

      public function plus(self $other): self;
      public function minus(self $other): self;
      public function multipliedBy(string|int|float $factor): self;
      public function dividedBy(string|int|float $divisor): self;
      public function roundedToScale(int $scale): self;

      public function isZero(): bool;
      public function isNegative(): bool;
      public function compareTo(self $other): int;

      public function toDecimal(): string;   // '1234.5600' — the persistence form
      public function toFloat(): float;      // presentation only, never arithmetic

      public int $minorUnits;
      public string $currency;
  }
  ```
  All rounding is half-away-from-zero and agrees with PHP's `round()` on every value
  tested — the divergence measured above comes from the *inputs* float arithmetic
  produces, not from the rounding step. `roundedToScale(0)`
  yields whole units still held as scale-4 minor units (e.g. `30000` for `3.0000`).
  `plus`, `minus` and `compareTo` throw `CurrencyMismatch` when currencies differ.

  **`fromHighPrecision()` is the class's most important method** and the one that makes
  the whole refactor safe. Callers that chain several operations (`LineCalculator`) must
  carry their intermediates as `PRECISION`-scale decimal strings and cross into `Money`
  exactly once, at the end. Rounding at every step instead — which is what the per-op
  methods do — was measured to diverge from today's output in 22% of cases.
  `roundDecimal()` is exposed so callers can round a high-precision string without
  constructing a `Money` first.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Support/MoneyTest.php`:

```php
<?php

declare(strict_types=1);

use App\Support\CurrencyMismatch;
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
        ->toThrow(CurrencyMismatch::class);
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
```

The "matches PHP round()" test is the one that matters most: it is the guarantee that
converting a call site cannot shift a figure the business has already seen.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Unit/Support/MoneyTest.php`
Expected: FAIL — `Class "App\Support\Money" not found`.

- [ ] **Step 3: Write the mismatch exception**

Create `app/Support/CurrencyMismatch.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Thrown when two Money values in different currencies are combined. Converting
 * between currencies is an explicit business operation with an exchange rate and
 * a rate date; it never happens implicitly inside an arithmetic operator.
 */
final class CurrencyMismatch extends RuntimeException
{
    public static function between(string $left, string $right): self
    {
        return new self(sprintf('Cannot combine %s and %s without an explicit conversion.', $left, $right));
    }
}
```

- [ ] **Step 4: Write the Money value object**

Create `app/Support/Money.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use DivisionByZeroError;

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
     */
    public static function fromHighPrecision(string $decimal, int $roundingScale, string $currency): self
    {
        return self::fromDecimal(self::roundDecimal($decimal, $roundingScale), $currency);
    }

    /**
     * Round a decimal string half-away-from-zero at an arbitrary scale, without
     * going through Money. Exposed so callers can round intermediates in place.
     */
    public static function roundDecimal(string $value, int $scale): string
    {
        $adjustment = bcdiv('5', bcpow('10', (string) ($scale + 1), 0), $scale + 1);

        if (bccomp($value, '0', self::PRECISION) < 0) {
            $adjustment = '-'.$adjustment;
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

    private static function normalise(string|int|float $value): string
    {
        return is_float($value)
            ? number_format($value, self::GUARD_SCALE, '.', '')
            : (string) $value;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }

    /**
     * bcadd at scale 0 truncates toward zero, so adding ±0.5 first yields
     * half-away-from-zero rounding — the same behaviour as PHP's round().
     */
    private static function roundHalfAwayFromZero(string $value): string
    {
        $adjustment = bccomp($value, '0', self::GUARD_SCALE) >= 0 ? '0.5' : '-0.5';

        return bcadd($value, $adjustment, 0);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Unit/Support/MoneyTest.php`
Expected: all 18 tests PASS.

If "matches PHP round()" fails on `2.5` at scale 0, PHP's `round()` is
half-away-from-zero and yields `3.0` — confirm `roundHalfAwayFromZero` is being reached
rather than a truncating `bcdiv`.

- [ ] **Step 6: Declare the extension dependency**

In `composer.json`, add to the `require` block (the `ext-` entries sort before named
packages):

```json
        "ext-bcmath": "*",
```

Run: `php -r "var_dump(extension_loaded('bcmath'));"`
Expected: `bool(true)`. It is installed at `Dockerfile:54`; this step only makes the
dependency explicit so a future image change cannot silently remove it.

- [ ] **Step 7: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Support/Money.php app/Support/CurrencyMismatch.php tests/Unit/Support/MoneyTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
php vendor/bin/pest --type-coverage --min=99.9
git add app/Support/Money.php app/Support/CurrencyMismatch.php tests/Unit/Support/MoneyTest.php composer.json
git commit -m "feat: add exact Money value object backed by integer minor units"
```

---

### Task 2: Exact line calculation

**Files:**
- Modify: `app/Services/Erp/Financial/LineCalculator.php`
- Modify: `app/Services/Erp/Financial/LineAmounts.php`
- Test: `tests/Unit/Erp/Financial/LineCalculatorTest.php` (existing — extend, do not replace)

**Interfaces:**
- Consumes: `App\Support\Money`
- Produces:
  ```php
  final readonly class LineCalculator
  {
      public function calculate(
          Money $unitPriceInput,
          PriceBasis $priceBasis,
          bool $taxable,
          string $taxRate,      // percentage as a decimal string, e.g. '11' or '11.5'
          string $quantity,     // decimal string, e.g. '3' or '2.5000'
          int $roundingScale,   // was $currencyDecimals — 0 for buyer, 4 for supplier
      ): LineAmounts;
  }

  final readonly class LineAmounts
  {
      public Money $unitPriceExcTax;
      public Money $taxAmountPerUnit;
      public Money $lineSubtotal;
      public Money $lineTax;
      public Money $lineTotal;
  }
  ```
  The parameter order matches today's, with `currencyDecimals` renamed to
  `roundingScale` in the same position. All five results are rounded to
  `$roundingScale`; `lineTotal` is derived as `lineSubtotal->plus($lineTax)` **after**
  both are rounded, preserving today's guarantee that the three reconcile exactly.

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Erp/Financial/LineCalculatorTest.php`:

```php
use App\Enums\Erp\PriceBasis;
use App\Services\Erp\Financial\LineCalculator;
use App\Support\Money;

describe('exact arithmetic', function (): void {
    it('reconciles subtotal plus tax to total exactly', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('33.3333', 'IDR'),
            priceBasis: PriceBasis::NET,
            taxable: true,
            taxRate: '11',
            quantity: '7',
            roundingScale: 4,
        );

        expect($amounts->lineSubtotal->plus($amounts->lineTax)->compareTo($amounts->lineTotal))
            ->toBe(0);
    });

    it('derives net from gross without float drift', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('111', 'IDR'),
            priceBasis: PriceBasis::GROSS,
            taxable: true,
            taxRate: '11',
            quantity: '1',
            roundingScale: 4,
        );

        expect($amounts->unitPriceExcTax->toDecimal())->toBe('100.0000')
            ->and($amounts->taxAmountPerUnit->toDecimal())->toBe('11.0000')
            ->and($amounts->lineTotal->toDecimal())->toBe('111.0000');
    });

    it('rounds to whole units at scale 0 the way buyer quotes do', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('33.5678', 'IDR'),
            priceBasis: PriceBasis::NET,
            taxable: false,
            taxRate: '0',
            quantity: '3',
            roundingScale: 0,
        );

        expect($amounts->unitPriceExcTax->toDecimal())->toBe('34.0000')
            ->and($amounts->lineSubtotal->toDecimal())->toBe('101.0000');
    });

    it('applies no tax when the line is not taxable', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('100', 'IDR'),
            priceBasis: PriceBasis::NET,
            taxable: false,
            taxRate: '11',
            quantity: '2',
            roundingScale: 4,
        );

        expect($amounts->lineTax->isZero())->toBeTrue()
            ->and($amounts->lineSubtotal->toDecimal())->toBe('200.0000')
            ->and($amounts->lineTotal->toDecimal())->toBe('200.0000');
    });

    it('applies no tax at a zero rate', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('100', 'IDR'),
            priceBasis: PriceBasis::GROSS,
            taxable: true,
            taxRate: '0',
            quantity: '1',
            roundingScale: 4,
        );

        expect($amounts->lineTax->isZero())->toBeTrue()
            ->and($amounts->unitPriceExcTax->toDecimal())->toBe('100.0000');
    });

    it('handles fractional quantities', function (): void {
        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal('10', 'IDR'),
            priceBasis: PriceBasis::NET,
            taxable: false,
            taxRate: '0',
            quantity: '2.5',
            roundingScale: 4,
        );

        expect($amounts->lineSubtotal->toDecimal())->toBe('25.0000');
    });
});
```

`PriceBasis::NET` and `PriceBasis::GROSS` are the cases used at the existing call sites
(`app/Models/SupplierQuoteItem.php:283`), so they are confirmed, not assumed.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Unit/Erp/Financial/LineCalculatorTest.php`
Expected: FAIL — a `TypeError`, because `calculate()` still declares `float` parameters.

- [ ] **Step 3: Rewrite `LineAmounts`**

Replace `app/Services/Erp/Financial/LineAmounts.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Support\Money;

/**
 * Immutable result of a single line calculation. All amounts are already rounded
 * to the caller's rounding scale; lineTotal is derived from the rounded subtotal
 * and tax so that lineSubtotal + lineTax === lineTotal exactly.
 */
final readonly class LineAmounts
{
    public function __construct(
        public Money $unitPriceExcTax,
        public Money $taxAmountPerUnit,
        public Money $lineSubtotal,
        public Money $lineTax,
        public Money $lineTotal,
    ) {}
}
```

- [ ] **Step 4: Rewrite `LineCalculator`**

Replace `app/Services/Erp/Financial/LineCalculator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Enums\Erp\PriceBasis;
use App\Support\Money;

/**
 * The single source of truth for per-line tax and total arithmetic across the
 * buyer-quote and supplier-quote document types.
 *
 * Each model maps its own tax flags onto an explicit price basis and a taxable
 * boolean (the two families assign opposite meanings to is_tax_inclusive), so
 * this one engine reproduces both conventions without any second formula.
 *
 * Arithmetic is exact: amounts are Money (integer minor units), and the tax rate
 * and quantity are decimal strings, so no step passes through a binary float.
 * The previous float implementation accumulated error into margin, which is the
 * number this business sells.
 *
 * $roundingScale is a business rule, not a precision detail: buyer quotes round
 * to whole units, supplier quotes to four decimals. It is applied to the
 * per-unit figures before they are multiplied out, which is what the float
 * implementation did, so converted call sites produce identical figures.
 */
final readonly class LineCalculator
{
    public function calculate(
        Money $unitPriceInput,
        PriceBasis $priceBasis,
        bool $taxable,
        string $taxRate,
        string $quantity,
        int $roundingScale,
    ): LineAmounts {
        $precision = Money::PRECISION;
        $currency = $unitPriceInput->currency;
        $price = $unitPriceInput->toDecimal();

        $applyTax = $taxable && bccomp($taxRate, '0', 8) === 1;

        if ($applyTax && $priceBasis === PriceBasis::GROSS) {
            // net = gross / (1 + rate/100)
            $divisor = bcadd('1', bcdiv($taxRate, '100', $precision), $precision);
            $rawExcTax = bcdiv($price, $divisor, $precision);
            $rawTaxPerUnit = bcsub($price, $rawExcTax, $precision);
        } elseif ($applyTax) {
            $rawExcTax = $price;
            $rawTaxPerUnit = bcdiv(bcmul($price, $taxRate, $precision), '100', $precision);
        } else {
            $rawExcTax = $price;
            $rawTaxPerUnit = '0';
        }

        $lineSubtotal = Money::fromHighPrecision(
            bcmul($rawExcTax, $quantity, $precision), $roundingScale, $currency,
        );
        $lineTax = Money::fromHighPrecision(
            bcmul($rawTaxPerUnit, $quantity, $precision), $roundingScale, $currency,
        );

        return new LineAmounts(
            unitPriceExcTax: Money::fromHighPrecision($rawExcTax, $roundingScale, $currency),
            taxAmountPerUnit: Money::fromHighPrecision($rawTaxPerUnit, $roundingScale, $currency),
            lineSubtotal: $lineSubtotal,
            lineTax: $lineTax,
            lineTotal: $lineSubtotal->plus($lineTax),
        );
    }
}
```

**Why the intermediates are decimal strings and not `Money`.** The float version
computed `round($rawExcTax * $quantity, $decimals)` — full precision through the
multiply, rounded once at the end. `Money` rounds on every operation, so
`$price->dividedBy($divisor)->multipliedBy($quantity)` rounds the per-unit figure to
scale 4 *before* multiplying out, which shifts the line total. Measured, that version
diverged from today's output in 583 of 2 592 cases; this one diverges in 59 of 16 800,
all by exactly one unit in the last place. Do not "simplify" this method by moving the
intermediates into `Money`.

- [ ] **Step 5: Run the new tests**

Run: `php vendor/bin/pest tests/Unit/Erp/Financial/LineCalculatorTest.php`
Expected: the six new tests PASS. **The pre-existing tests in this file will fail with
`TypeError`** — they pass floats. Do not delete them; Task 3 converts them. Before
moving on, record every amount those pre-existing tests assert — they are the parity
oracle.

- [ ] **Step 6: Commit the engine (call sites still broken — expected)**

```bash
php vendor/bin/rector process app/Services/Erp/Financial/LineCalculator.php app/Services/Erp/Financial/LineAmounts.php
php vendor/bin/pint --dirty
git add app/Services/Erp/Financial/LineCalculator.php app/Services/Erp/Financial/LineAmounts.php tests/Unit/Erp/Financial/LineCalculatorTest.php
git commit -m "refactor: make LineCalculator exact with Money and an explicit rounding scale"
```

This commit intentionally leaves the four call sites uncompiled against the new
signature. Task 3 lands immediately after; **do not deploy between the two.**

---

### Task 3: Convert the four LineCalculator call sites

**Files:**
- Modify: `app/Models/BuyerQuoteItem.php:288-316` (`recalculatePrices`)
- Modify: `app/Models/SupplierQuoteItem.php:264-296` (`calculateTotals`)
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php:2705`
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php:2752`
- Modify: `tests/Unit/Erp/Financial/LineCalculatorTest.php` — convert the pre-existing float tests

**Interfaces:**
- Consumes: `LineCalculator::calculate(Money, PriceBasis, bool, string, string, int): LineAmounts`
- Produces: no signature changes on the models. Values are persisted with
  `LineAmounts::$x->toDecimal()`, a string, which PostgreSQL accepts directly into a
  `decimal(18,4)` column.

- [ ] **Step 1: Convert `BuyerQuoteItem::recalculatePrices()`**

In `app/Models/BuyerQuoteItem.php`, replace the whole method body:

```php
    /**
     * Recalculate all price-related fields based on current values.
     */
    public function recalculatePrices(): void
    {
        // Buyer items: unit_price is always the net (ex-tax) price, and the
        // is_tax_inclusive "+ Tax" checkbox decides whether tax is added on top.
        // Buyer documents round to whole units — see LineCalculator's note on
        // $roundingScale; this was currencyDecimals: 0 before the Money refactor.
        $currency = $this->buyerQuote?->currency?->code ?? 'IDR';
        $taxRate = (string) $this->tax_rate;

        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal($this->unit_price, $currency),
            priceBasis: PriceBasis::NET,
            taxable: $this->is_tax_inclusive && bccomp($taxRate, '0', Money::SCALE) === 1,
            taxRate: $taxRate,
            quantity: (string) $this->quantity,
            roundingScale: 0,
        );

        $this->unit_price_exc_tax = $amounts->unitPriceExcTax->toDecimal();
        $this->line_subtotal = $amounts->lineSubtotal->toDecimal();
        $this->line_tax = $amounts->lineTax->toDecimal();
        $this->line_total = $amounts->lineTotal->toDecimal();
        $this->tax_amount = $amounts->taxAmountPerUnit->toDecimal();

        $costPrice = Money::fromDecimal($this->cost_price, $currency);
        $this->margin_amount = $amounts->unitPriceExcTax
            ->minus($costPrice)
            ->roundedToScale(0)
            ->toDecimal();
        $this->margin_percent = (string) round(
            MarginConvention::marginPercent(
                $costPrice->toFloat(),
                $amounts->unitPriceExcTax->toFloat(),
            ),
            4,
        );
    }
```

Add to the imports:

```php
use App\Support\Money;
```

Confirm the currency accessor path before running — check with
`grep -n "function currency" app/Models/BuyerQuote.php`. If the relation or the code
column differs, use the real one. The fallback `'IDR'` matters only for rows whose quote
has no currency; since `Money` never converts implicitly, a wrong fallback surfaces as a
`CurrencyMismatch`, not as a silently wrong number.

- [ ] **Step 2: Run the buyer-quote financial tests**

```bash
php vendor/bin/pest tests/Feature/Erp/BuyerQuotePdfTotalTest.php tests/Feature/Erp/BuyerQuoteMarginSeedTest.php tests/Feature/Filament/App/Resources/BuyerQuoteMarginFormTest.php
```
Expected: PASS **with no expectation edits**. These assert end-to-end money values
through PDFs and margin seeding — passing them unmodified is the evidence that
`roundingScale: 0` reproduces the old `currencyDecimals: 0` behaviour exactly.

- [ ] **Step 3: Convert `SupplierQuoteItem::calculateTotals()`**

In `app/Models/SupplierQuoteItem.php`, replace from the `$taxRate = (float)` line to the
end of the method:

```php
        // Supplier prices may be entered gross (tax-inclusive) or net; map the
        // stored flag onto the shared calculator's explicit price basis.
        // Supplier documents keep four decimals — this was currencyDecimals: 4.
        $currency = $this->supplierQuote?->currency?->code ?? 'IDR';
        $taxRate = (string) $this->tax_rate;

        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal($this->unit_price, $currency),
            priceBasis: $this->is_tax_inclusive ? PriceBasis::GROSS : PriceBasis::NET,
            taxable: $isSupplierTaxable && bccomp($taxRate, '0', Money::SCALE) === 1,
            taxRate: $taxRate,
            quantity: (string) $this->quantity,
            roundingScale: 4,
        );

        $this->unit_price_exc_tax = $amounts->unitPriceExcTax->toDecimal();
        $this->line_subtotal = $amounts->lineSubtotal->toDecimal();
        $this->line_tax = $amounts->lineTax->toDecimal();
        $this->line_total = $amounts->lineTotal->toDecimal();
        $this->tax_amount = $amounts->taxAmountPerUnit->toDecimal();
    }
```

Leave the `$isSupplierTaxable` resolution and the non-taxable clearing block above
untouched. Add `use App\Support\Money;` to the imports.

- [ ] **Step 4: Convert both relation-manager call sites**

At `BuyerQuotesRelationManager.php:2705` and `:2752`, apply the same transformation as
Step 1: wrap `unitPriceInput` in `Money::fromDecimal(...)`, pass `taxRate` and
`quantity` as strings, and pass `roundingScale: 0` — these are buyer-quote previews and
must match the observer that persists the row. Read each site first with
`grep -n -A 14 "new LineCalculator" app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php`
to see which form-state keys receive the results.

These previews feed Filament form state, so the results are displayed rather than
persisted: use `->toFloat()` where the form component expects a numeric value and
`->toDecimal()` where it expects a string. The comments at `:2704` and `:2749` state
these must stay on the same engine as the observer — that is exactly why both are
converted rather than left on a float path.

- [ ] **Step 5: Convert the pre-existing LineCalculator tests**

Convert every pre-existing test in `tests/Unit/Erp/Financial/LineCalculatorTest.php` to
the new signature: wrap price inputs in `Money::fromDecimal(...)`, pass `taxRate` and
`quantity` as strings, rename `currencyDecimals:` to `roundingScale:` keeping its value,
and assert on `->toDecimal()` strings rather than floats.

**An asserted amount that changes must be proven to be a one-ULP tie before it is
edited.** The measured expectation is that almost none change (0.02% of buyer-path
fields, 0.68% of supplier-path fields). For each one that does:

1. Compute the exact value by hand or with `bcmul`/`bcdiv` at scale 20.
2. Confirm the difference is exactly one unit in the last place — `1` at scale 0,
   `0.0001` at scale 4.
3. Confirm the exact value sits on a `.5` boundary at the rounding scale.

If all three hold, the old expectation was a float artefact: update it, and record the
old and new value in the commit message and the release notes. **If any of the three
fails, the conversion has a real bug** — most likely an intermediate that went through
`Money` instead of staying a decimal string. Do not edit the expectation.

- [ ] **Step 6: Run the financial unit and feature tests**

```bash
php vendor/bin/pest tests/Unit/Erp/Financial
php vendor/bin/pest tests/Feature/Erp/BuyerQuotePdfTotalTest.php tests/Feature/Erp/BuyerOrderPdfTotalTest.php tests/Feature/Erp/LineItemReconciliationTest.php tests/Feature/Erp/BuyerQuoteMarginSeedTest.php tests/Feature/Erp/ProfitAndLossPdfMarginTest.php
```
Expected: PASS.

- [ ] **Step 7: Run the full suite**

Run: `php vendor/bin/pest --parallel`
Expected: PASS.

- [ ] **Step 8: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Models/BuyerQuoteItem.php app/Models/SupplierQuoteItem.php app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php tests/Unit/Erp/Financial/LineCalculatorTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Models/BuyerQuoteItem.php app/Models/SupplierQuoteItem.php app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php tests/Unit/Erp/Financial/LineCalculatorTest.php
git commit -m "refactor: feed the four LineCalculator call sites exact Money values"
```

---

### Task 4: Exact document totals

**Files:**
- Modify: `app/Services/Erp/Financial/TotalsLine.php`
- Modify: `app/Services/Erp/Financial/TotalsCollector.php`
- Modify: `app/Services/Erp/Financial/DocumentTotals.php`
- Modify: `app/Models/BuyerQuoteItem.php:488-500` (`collectTotals`)
- Modify: `app/Models/BuyerQuote.php:602`
- Modify: `app/Models/SupplierQuote.php:339`
- Test: `tests/Unit/Erp/Financial/TotalsCollectorTest.php` (existing — extend and convert)

**Interfaces:**
- Consumes: `App\Support\Money`, `MarginConvention::marginPercent(float, float): float`
- Produces:
  ```php
  final readonly class TotalsLine
  {
      public function __construct(
          public Money $lineSubtotal,
          public Money $lineTax,
          public Money $lineTotal,
          public Money $costPrice,
          public string $quantity,   // decimal string
      ) {}
  }

  final readonly class DocumentTotals
  {
      public Money $subtotal;
      public Money $taxTotal;
      public Money $grandTotal;
      public Money $costTotal;
      public Money $marginAmount;
      public float $marginPercent;   // a ratio, deliberately still float
  }

  final readonly class TotalsCollector
  {
      /** @param Collection<int, TotalsLine> $lines */
      public function collect(Collection $lines, string $currency): DocumentTotals;
  }
  ```
  `collect()` gains a `$currency` parameter so an empty line collection still returns a
  well-formed zero total. It takes no rounding scale: it sums lines that the caller has
  already rounded, which is what the float version did.

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Erp/Financial/TotalsCollectorTest.php`:

```php
use App\Services\Erp\Financial\TotalsCollector;
use App\Services\Erp\Financial\TotalsLine;
use App\Support\Money;
use Illuminate\Support\Collection;

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Unit/Erp/Financial/TotalsCollectorTest.php`
Expected: FAIL — `TypeError`; `collect()` takes one argument and `TotalsLine` takes
floats.

- [ ] **Step 3: Rewrite `TotalsLine`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Support\Money;

/**
 * A single already-calculated line handed to TotalsCollector. Callers map their
 * model rows (main items only) onto this shape; the collector stays free of any
 * model or column-name coupling.
 *
 * quantity is a decimal string rather than Money — it is a count, not an amount.
 */
final readonly class TotalsLine
{
    public function __construct(
        public Money $lineSubtotal,
        public Money $lineTax,
        public Money $lineTotal,
        public Money $costPrice,
        public string $quantity,
    ) {}
}
```

- [ ] **Step 4: Rewrite `DocumentTotals`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Support\Money;

/**
 * Aggregated document-level totals in the document's transaction currency.
 *
 * Margin fields are meaningful only for buyer documents (which carry a
 * cost-vs-sell relationship); supplier documents consume only subtotal,
 * taxTotal, and grandTotal. Any base-currency (FX) conversion is applied by the
 * document model to these figures — TotalsCollector is FX-agnostic.
 *
 * marginPercent is deliberately a float: it is a ratio, not an amount, and
 * carries no minor units to be exact about.
 */
final readonly class DocumentTotals
{
    public function __construct(
        public Money $subtotal,
        public Money $taxTotal,
        public Money $grandTotal,
        public Money $costTotal,
        public Money $marginAmount,
        public float $marginPercent,
    ) {}
}
```

- [ ] **Step 5: Rewrite `TotalsCollector`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Aggregates a pre-filtered collection of lines into document-level totals.
 *
 * The caller is responsible for filtering to main items only (e.g. via
 * BuyerQuoteItem::filterForTotals) before calling collect(); this service
 * applies no parent_id filter of its own.
 *
 * Summation is exact: an amount is only ever added to another amount of the same
 * currency, so a hundred repeating-decimal lines total to the same figure a
 * human gets with a calculator. Lines arrive already rounded to their document's
 * scale, so no rounding happens here.
 *
 * @see DocumentTotals for the FX and margin scope notes.
 */
final readonly class TotalsCollector
{
    /**
     * @param  Collection<int, TotalsLine>  $lines
     */
    public function collect(Collection $lines, string $currency): DocumentTotals
    {
        $subtotal = Money::zero($currency);
        $taxTotal = Money::zero($currency);
        $grandTotal = Money::zero($currency);
        $costTotal = Money::zero($currency);

        foreach ($lines as $line) {
            $subtotal = $subtotal->plus($line->lineSubtotal);
            $taxTotal = $taxTotal->plus($line->lineTax);
            $grandTotal = $grandTotal->plus($line->lineTotal);
            $costTotal = $costTotal->plus($line->costPrice->multipliedBy($line->quantity));
        }

        return new DocumentTotals(
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            grandTotal: $grandTotal,
            costTotal: $costTotal,
            marginAmount: $subtotal->minus($costTotal),
            marginPercent: MarginConvention::marginPercent($costTotal->toFloat(), $subtotal->toFloat()),
        );
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Unit/Erp/Financial/TotalsCollectorTest.php`
Expected: the five new tests PASS; pre-existing tests in the file fail on the changed
signature — convert them in Step 8.

- [ ] **Step 7: Convert the three collect() call sites**

At each of `app/Models/BuyerQuoteItem.php:492`, `app/Models/BuyerQuote.php:602` and
`app/Models/SupplierQuote.php:339`, the `->map(fn (...) => new TotalsLine(...))` closure
passes model attributes directly. Wrap each money argument in
`Money::fromDecimal($value, $currency)`, cast `quantity` to `(string)`, and pass the
document's currency code as `collect()`'s second argument. Resolve `$currency` the same
way Task 3 did — `$this->currency?->code ?? 'IDR'` on the document models, and the
parent quote's currency in `BuyerQuoteItem::collectTotals()`.

Then update the consumers of the returned `DocumentTotals` in each method: assignments
to `subtotal`, `tax_total`, `total`, `margin_amount` and similar columns become
`$totals->subtotal->toDecimal()` and so on. `$totals->marginPercent` is already a float
and needs no change.

Add `use App\Support\Money;` to each of the three files.

- [ ] **Step 8: Convert the pre-existing TotalsCollector tests**

Same rule as Task 3 Step 5: wrap inputs in `Money::fromDecimal(...)`, assert on
`->toDecimal()`, and **do not change an asserted amount** without deciding whether the
old value was a float artefact or the new code is wrong. Record any change in the commit
message.

- [ ] **Step 9: Run the financial suites**

```bash
php vendor/bin/pest tests/Unit/Erp/Financial
php vendor/bin/pest tests/Feature/Erp/BuyerQuotePdfTotalTest.php tests/Feature/Erp/BuyerOrderPdfTotalTest.php tests/Feature/Erp/LineItemReconciliationTest.php tests/Feature/Erp/ProfitAndLossPdfMarginTest.php
```
Expected: PASS.

- [ ] **Step 10: Run the full suite with all gates**

```bash
php vendor/bin/pest --parallel
php vendor/bin/phpstan analyse
php vendor/bin/pest --type-coverage --min=99.9
php vendor/bin/pest --filter=arch
```
Expected: all PASS.

- [ ] **Step 11: Lint and commit**

```bash
php vendor/bin/rector process app/Services/Erp/Financial app/Models/BuyerQuoteItem.php app/Models/BuyerQuote.php app/Models/SupplierQuote.php tests/Unit/Erp/Financial
php vendor/bin/pint --dirty
git add app/Services/Erp/Financial app/Models/BuyerQuoteItem.php app/Models/BuyerQuote.php app/Models/SupplierQuote.php tests/Unit/Erp/Financial
git commit -m "refactor: make document totals exact with Money summation"
```

---

### Task 5: Lock the invariants in

The properties this refactor buys are only worth what enforces them. These are the
`I-#` statements of §9.3 of the reference architecture: asserted continuously, not
proven once.

**Files:**
- Create: `tests/Feature/Erp/Financial/MoneyInvariantsTest.php`
- Modify: `tests/ArchTest.php`

**Interfaces:**
- Consumes: everything built in Tasks 1–4
- Produces: no runtime interface. Produces two CI gates: a property test over generated
  line data, and an arch rule preventing a float from re-entering the financial
  services.

- [ ] **Step 1: Write the invariant test**

Create `tests/Feature/Erp/Financial/MoneyInvariantsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\Erp\PriceBasis;
use App\Services\Erp\Financial\LineCalculator;
use App\Services\Erp\Financial\TotalsCollector;
use App\Services\Erp\Financial\TotalsLine;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * I-M1: for every line, lineSubtotal + lineTax === lineTotal
 * I-M2: for every document, SUM(line subtotals) === document subtotal, and the
 *       same for tax and grand total
 * I-M3: marginAmount === subtotal - costTotal
 * I-M4: no monetary result is ever NaN or infinite
 *
 * Both rounding scales in production use (0 for buyer documents, 4 for supplier
 * documents) are exercised — a rounding scale that broke reconciliation would
 * otherwise only surface on one side of the business.
 */
$prices = ['0.0001', '0.3333', '1', '19.99', '111', '1234.5678', '999999.9999'];
$rates = ['0', '10', '11', '11.5', '21'];
$quantities = ['1', '3', '2.5', '7.3333', '100'];
$scales = [0, 4];

it('I-M1: line subtotal plus tax always equals line total', function () use ($prices, $rates, $quantities, $scales): void {
    $calculator = new LineCalculator;

    foreach ($prices as $price) {
        foreach ($rates as $rate) {
            foreach ($quantities as $quantity) {
                foreach ($scales as $scale) {
                    foreach ([PriceBasis::NET, PriceBasis::GROSS] as $basis) {
                        $amounts = $calculator->calculate(
                            unitPriceInput: Money::fromDecimal($price, 'IDR'),
                            priceBasis: $basis,
                            taxable: true,
                            taxRate: $rate,
                            quantity: $quantity,
                            roundingScale: $scale,
                        );

                        expect($amounts->lineSubtotal->plus($amounts->lineTax)->compareTo($amounts->lineTotal))
                            ->toBe(0, "price={$price} rate={$rate} qty={$quantity} scale={$scale} basis={$basis->name}");
                    }
                }
            }
        }
    }
});

it('I-M2 and I-M3: document totals reconcile to their lines', function () use ($prices, $rates, $scales): void {
    $calculator = new LineCalculator;
    $collector = new TotalsCollector;

    foreach ($rates as $rate) {
        foreach ($scales as $scale) {
            $lines = new Collection;
            $expectedSubtotal = Money::zero('IDR');
            $expectedTax = Money::zero('IDR');

            foreach ($prices as $price) {
                $amounts = $calculator->calculate(
                    unitPriceInput: Money::fromDecimal($price, 'IDR'),
                    priceBasis: PriceBasis::NET,
                    taxable: true,
                    taxRate: $rate,
                    quantity: '3',
                    roundingScale: $scale,
                );

                $lines->push(new TotalsLine(
                    lineSubtotal: $amounts->lineSubtotal,
                    lineTax: $amounts->lineTax,
                    lineTotal: $amounts->lineTotal,
                    costPrice: Money::fromDecimal('1', 'IDR'),
                    quantity: '3',
                ));

                $expectedSubtotal = $expectedSubtotal->plus($amounts->lineSubtotal);
                $expectedTax = $expectedTax->plus($amounts->lineTax);
            }

            $totals = $collector->collect($lines, 'IDR');

            expect($totals->subtotal->compareTo($expectedSubtotal))->toBe(0, "rate={$rate} scale={$scale}")
                ->and($totals->taxTotal->compareTo($expectedTax))->toBe(0)
                ->and($totals->subtotal->plus($totals->taxTotal)->compareTo($totals->grandTotal))->toBe(0)
                ->and($totals->marginAmount->compareTo($totals->subtotal->minus($totals->costTotal)))->toBe(0);
        }
    }
});

it('I-M4: no monetary result is NaN or infinite', function () use ($prices, $rates, $quantities): void {
    $calculator = new LineCalculator;

    foreach ($prices as $price) {
        foreach ($rates as $rate) {
            foreach ($quantities as $quantity) {
                $amounts = $calculator->calculate(
                    unitPriceInput: Money::fromDecimal($price, 'IDR'),
                    priceBasis: PriceBasis::GROSS,
                    taxable: true,
                    taxRate: $rate,
                    quantity: $quantity,
                    roundingScale: 4,
                );

                foreach ([$amounts->lineSubtotal, $amounts->lineTax, $amounts->lineTotal] as $money) {
                    expect(is_finite($money->toFloat()))->toBeTrue();
                }
            }
        }
    }
});
```

- [ ] **Step 2: Run the invariant test**

Run: `php vendor/bin/pest tests/Feature/Erp/Financial/MoneyInvariantsTest.php`
Expected: PASS. A failure names the exact price/rate/quantity/scale combination that
breaks reconciliation — that is the test's whole purpose, so read the message rather
than loosening the assertion.

- [ ] **Step 3: Add the arch rule keeping floats out**

Append to `tests/ArchTest.php`:

```php
arch('financial services do not use floats')
    ->expect('App\Services\Erp\Financial')
    ->not
    ->toUse(['floatval', 'round'])
    ->ignoring('App\Services\Erp\Financial\MarginConvention');
```

`MarginConvention` is exempted deliberately: a margin percentage is a ratio, and
expressing it as an integer minor unit would be a category error. Everything else in
that namespace deals in amounts and must go through `Money`.

- [ ] **Step 4: Run the arch tests**

Run: `php vendor/bin/pest --filter=arch`
Expected: PASS. If it fails, a `round()` or `floatval()` survived a conversion in
Tasks 2–4 — find it and route it through `Money` rather than adding it to the ignore
list.

- [ ] **Step 5: Full gate run**

```bash
php vendor/bin/pest --parallel
php vendor/bin/pest --coverage --min=80
php vendor/bin/pest --type-coverage --min=99.9
php vendor/bin/phpstan analyse
php vendor/bin/rector --dry-run
php vendor/bin/pint --test
```
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Erp/Financial/MoneyInvariantsTest.php tests/ArchTest.php
git commit -m "test: lock money reconciliation invariants and bar floats from the financial services"
```

---

## Deployment note

This plan writes no migration and changes no column. It is safe to deploy in one release
**provided Tasks 2 and 3 ship together** — Task 2 alone leaves four call sites passing
floats to a `Money` parameter, which is a fatal `TypeError` on the first quote save.

The parity evidence is the existing PDF and reconciliation feature tests passing
unmodified (Task 3 Steps 2 and 6, Task 4 Step 9), backed by the 16,800-field grid
measured in *Measured impact* above.

**Tell finance before, not after.** Stored values do not move on deploy — line amounts
recalculate on save, so a historical document changes only if it is edited. On
recalculation, roughly one buyer-quote line in five thousand shifts by one rupiah,
always upward, always because the old value was rounded the wrong way at an exact `.5`.
That is a small, defensible, one-directional change, and it is much easier to explain in
advance than to explain after someone spots a one-rupiah delta on a re-saved quote.

## Explicitly out of scope

- **FX conversion.** `exchange_rate` is `decimal(20,10)` and base-currency columns
  (`base_subtotal`, `base_tax_total`, `base_total`) are computed by the document models.
  Making that path exact is a second change with its own rounding-policy question
  (convert-then-round per line, or round the document total), and it should be decided
  with the business, not inferred. Record it as an OpenSpec follow-up.
- **`margin_percent` as an exact type.** It is a ratio; float is correct for it.
- **Migrating the columns to bigint minor units.** PostgreSQL `decimal(18,4)` is already
  exact. Changing the storage type would be churn with no correctness gain.
- **Per-currency display scale.** `Currency` carries a decimals field used for
  formatting. This plan preserves the existing per-document-family rounding scales
  (0 and 4) exactly as the call sites pass them today; unifying them with the currency's
  own decimals is a separate business decision.

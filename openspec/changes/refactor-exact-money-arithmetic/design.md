# Design: Exact Money Arithmetic

> Retroactive: this records the design decisions actually made and shipped, with
> the measured evidence that drove them.

## Context

Every money *column* was already exact — `decimal(18,4)` on PostgreSQL is
arbitrary-precision. The defect lived entirely in `App\Services\Erp\Financial`:
`LineCalculator` took `float` inputs and divided/multiplied with native PHP
operators; `TotalsCollector` summed `float` line amounts with `+`. Binary floating
point cannot represent most decimal fractions exactly, and the error this
introduces lands on `margin` — the number this trading-intermediary business
sells. This is `§5` of the sibling `pos` project's architecture doc — "money as
integers (minor units) plus a currency code, never floats" — applied to the one
part of erpc where it actually pays.

## Goals / Non-Goals

- Goals:
  - Eliminate binary-float arithmetic from `LineCalculator` and `TotalsCollector`.
  - Preserve the existing `roundingScale` business rule (0 for buyer, 4 for
    supplier) exactly — this is not a precision detail to normalise away.
  - Make the output difference from the prior float engine bounded and
    understood, not merely "close enough."
  - Keep `MarginConvention` a float ratio — it has no minor units.
- Non-Goals:
  - FX/base-currency conversion (`exchange_rate` is `decimal(20,10)`; still
    computed by the document models on top of `TotalsCollector`'s
    transaction-currency output — a separate rounding-policy question).
  - Migrating `SupplierOrder`/`SupplierOrderItem` (no line-item observer, no
    `line_subtotal`/`line_tax`/`cost_price` columns).
  - Changing the margin formula, the rounding-scale-per-family policy, or any
    tax-basis mapping — all of that was already correct (established by the
    2026-07-04 `refactor-financial-calculation-layer` change) and is untouched
    here.

## Decisions

### Decision: a `Money` value object, integer minor units at scale 4

`App\Support\Money` (`app/Support/Money.php`) holds an `int $minorUnits` and a
`string $currency`. All arithmetic (`plus`, `minus`, `multipliedBy`,
`dividedBy`, `roundedToScale`) runs through `bcmath`, never a native `+`/`-`/`*`/`/`
on a float. A float may be accepted at a boundary (`fromDecimal()`,
`multipliedBy()`) — callers still read floats out of existing Eloquent casts —
but it is stringified via `number_format()` at `GUARD_SCALE` (6) precision and
never itself participates in an operation. Combining two different currencies
throws `InvalidArgumentException` directly (no dedicated exception type: the app
has no `App\Exceptions` namespace and no established exemption from the
"avoid inheritance" architecture rule for a single-purpose exception under
`App\Support`).

Rounding is half-away-from-zero (matching PHP's `round()`), implemented as
`bcadd($value, ±0.5, 0)` — `bcadd` at scale 0 truncates toward zero, so adding
`±0.5` first reproduces `round()`'s tie-breaking.

### Decision: intermediates are decimal strings, not `Money` — the rejected alternative

**Rejected, with measured evidence.** The first draft made `LineCalculator`'s
intermediate values `Money` objects too — `$unitPrice->dividedBy($divisor)` then
`->multipliedBy($quantity)`. Validated against the prior float implementation
over a 2,592-field grid, this diverged in **583 cases (22%)**, some by 0.001 or
more — far beyond the one-ULP divergence a correctness fix should produce.

The cause: `Money`'s own arithmetic methods round to scale 4 on *every*
operation (that is what makes `Money` safe to hold as a persisted amount). Doing
`dividedBy()` then `multipliedBy()` rounds the per-unit net-of-tax price *before*
multiplying by quantity. The float implementation being replaced multiplied
first and rounded once — rounding a per-unit figure before scaling by quantity
is measurably not the same operation as rounding the scaled total.

**Fix:** `LineCalculator` computes every intermediate (`rawExcTax`,
`rawTaxPerUnit`, the post-multiply subtotal and tax) as a `Money::PRECISION`
(20-scale) decimal string via `bcdiv`/`bcmul`/`bcsub`, and crosses into `Money`
exactly once per output value through the new `Money::fromHighPrecision()` — the
one designated rounding boundary. This reproduces the float engine's "full
precision through the multiply, round once at the end" ordering exactly, using
exact arithmetic instead of binary-float arithmetic for that same ordering.

### Decision: `roundingScale` is a business rule, passed explicitly, never normalised

`LineCalculator::calculate()` keeps taking an explicit `roundingScale` (renamed
from the pre-existing `currencyDecimals`, same parameter position and same
values). The two document families pass different values:

| Call site | `roundingScale` | Why |
|---|---|---|
| `BuyerQuoteItem::recalculatePrices()` | `0` | Buyer quote lines round to whole rupiah — this is what customers have already been quoted |
| `SupplierQuoteItem::calculateTotals()` | `4` | Supplier quote lines keep four decimals |

Collapsing these to one scale would have re-rounded every existing buyer quote
line and silently changed figures already sent to customers. The parameter is
therefore a first-class input to `calculate()`, not something the refactor is
permitted to infer or unify.

### Decision: `MarginConvention` stays float; `Money::toFloat()` is the one documented crossing point

`marginPercent` is a ratio (`(sellNet − cost) / sellNet × 100`), not an amount —
it carries no minor units to be exact about, so there is nothing for `Money` to
buy here. `TotalsCollector::collect()` and `BuyerQuoteItem::recalculatePrices()`
both call `Money::toFloat()` on their `Money` inputs immediately before handing
them to `MarginConvention`. `Money::toFloat()`'s own docblock states it is
"presentation only. Never feed this back into arithmetic — that is the defect
this class exists to remove," and the new architecture test
(`tests/ArchTest.php`, `financial services do not use floats`) enforces that no
other float arithmetic exists in `App\Services\Erp\Financial`, exempting only
`MarginConvention`.

## Measured Impact

Validated against the shipping float implementation over a 16,800-field grid
(10 prices × 6 tax rates × 7 quantities × 2 scales × 2 price bases × taxable
on/off) before this landed:

| Path | Fields | Differ from prior float output | Largest difference |
|---|---|---|---|
| Buyer quotes (`roundingScale: 0`) | 8,400 | 2 (0.02%) | 1 rupiah |
| Supplier quotes (`roundingScale: 4`) | 8,400 | 57 (0.68%) | 0.0001 |

Every difference is exactly one unit in the last place. Invariant I-M1
(`lineSubtotal + lineTax === lineTotal`) held in all 16,800 fields, both before
and after.

**Mechanism, verified rather than assumed:** `0.3333 × 2.5` is exactly
`0.83325` — a tie that rounds up to `0.8333`. In binary floating point the
*product itself* computes to `0.83324999999999993516`, which sits below the
tie, so `round()` faithfully rounds the value it was handed *down* to
`0.8332`. `round()` is not at fault — float multiplication moved the product to
the wrong side of the rounding boundary before `round()` ever ran. In every
differing case the exact value is the arithmetically correct one.

**Consequence for existing data:** stored values do not move on deploy — line
amounts are recalculated on save, not backfilled — so a historical document
changes only if it is edited, and then by at most one unit in the last place,
always toward the correct rounding.

## Risks / Trade-offs

- **Output is not bit-for-bit identical to the prior implementation.** Mitigated
  by measuring the exact divergence (above) before shipping, and by the
  invariant suite (`MoneyInvariantsTest`) proving the identities that matter
  (`subtotal + tax === total`, document sums reconcile to line sums) hold
  regardless.
- **A future contributor could "simplify" `LineCalculator` back to `Money`
  intermediates**, reintroducing the 22% divergence. Mitigated by the class
  docblock recording the rejected alternative and by `Money::fromHighPrecision()`'s
  own docblock explaining why it is the designated single rounding boundary.
- **`round`/`floatval` could creep back into the financial services.** Mitigated
  by the `tests/ArchTest.php` rule barring both from `App\Services\Erp\Financial`,
  exempting only `MarginConvention`.

## Migration Plan

No backfill. Line amounts recalculate on save, so existing rows only pick up the
one-ULP correction when a line is next edited. The only schema change in this
body of work is unrelated to arithmetic exactness: widening
`buyer_quote_items.margin_percent` from `decimal(8,4)` to `decimal(12,4)` so a
cost-far-above-price line (verified: cost 600,000 against unit price 100 →
margin_percent -599900.0) does not overflow on PostgreSQL. That migration is
reversible (`down()` narrows the column back).

## Open Questions

- None outstanding for this change. FX/base-currency rounding policy and the
  `SupplierOrder` migration are explicitly out of scope and tracked as separate
  future changes, not open questions within this one.

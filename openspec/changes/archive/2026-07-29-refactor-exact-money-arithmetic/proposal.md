# Refactor Exact Money Arithmetic

> **Retroactive documentation.** This change is already implemented, tested, and
> merged to `master` (commits `1289b8b0`…`6577a51b`, 2026-07-29). It records shipped
> behaviour in the spec; it does not propose future work.

## Why

Storage was never the problem — every money column (`line_subtotal`, `line_tax`,
`line_total`, `unit_price_exc_tax`, `margin_amount`, etc.) is PostgreSQL
`decimal(18,4)`, which is arbitrary-precision. The defect was entirely on the PHP
side: `LineCalculator` took `float` unit prices, divided by `(1 + $taxRate / 100)`,
and `TotalsCollector` summed line amounts with `+`. Both operations are binary
floating point, and binary floating point cannot represent most decimal fractions
exactly.

For most software this would be cosmetic. For erpc it is not: the number this
business sells is the *margin* — the spread between a buyer quote and the supplier
quotes behind it — and float error lands directly on that spread. A tax-tie
rounding the wrong way, repeated across thousands of lines, is exactly the kind of
error a trading intermediary cannot absorb silently.

## What Changes

- **New `App\Support\Money` value object** (`app/Support/Money.php`): an immutable
  amount held as integer minor units at scale 4, matching `decimal(18,4)`. All
  arithmetic (`plus`, `minus`, `multipliedBy`, `dividedBy`, `roundedToScale`) goes
  through `bcmath`. A float may still be accepted at a boundary (`fromDecimal`,
  `multipliedBy`) because callers read floats out of existing Eloquent casts, but
  it is immediately normalised to a decimal string and never participates in an
  operation. Combining two different currencies throws `InvalidArgumentException`.
- **`LineCalculator` rewritten** (`app/Services/Erp/Financial/LineCalculator.php`):
  takes a `Money` unit price and numeric-string `taxRate`/`quantity` instead of
  floats. Every intermediate (net-of-tax price, tax per unit, line subtotal, line
  tax) stays a `Money::PRECISION` (20) scale decimal string computed with
  `bcdiv`/`bcmul`/`bcsub`, crossing into `Money` exactly once per output value
  through `Money::fromHighPrecision()`.
- **`TotalsCollector`, `TotalsLine`, `DocumentTotals` converted to `Money`**
  (`app/Services/Erp/Financial/{TotalsCollector,TotalsLine,DocumentTotals}.php`),
  plus all seven call sites that construct or consume them:
  - `app/Models/BuyerQuoteItem.php` (`recalculatePrices()`, `collectTotals()`)
  - `app/Models/SupplierQuoteItem.php` (`calculateTotals()`)
  - `app/Models/BuyerQuote.php` (`recalculateTotals()`)
  - `app/Models/SupplierQuote.php` (`recalculateTotals()`)
  - `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php`
    (two live-preview call sites, lines ~2705 and ~2752)
- **`roundingScale` is an explicit business rule, not a precision detail.**
  `LineCalculator::calculate()` keeps taking a `roundingScale` parameter (renamed
  from the pre-existing `currencyDecimals`, same position, same values): buyer
  quote lines pass `0` (whole rupiah), supplier quote lines pass `4`. Forcing a
  single scale would have re-rounded every already-quoted buyer line.
- **`MarginConvention` is unchanged and deliberately float.** `marginPercent` is a
  ratio, not a monetary amount — it has no minor units to be exact about. Its two
  callers (`TotalsCollector`, `BuyerQuoteItem::recalculatePrices()`) cross from
  `Money` to `float` via `Money::toFloat()` at the call site, which is the single
  documented place presentation values are allowed to touch a float.
- **New architecture test** (`tests/ArchTest.php`, `financial services do not use
  floats`): bars `floatval`/`round` from `App\Services\Erp\Financial`, exempting
  `MarginConvention`.
- **New property test**
  (`tests/Feature/Erp/Financial/MoneyInvariantsTest.php`) locking four invariants
  across both rounding scales and both price bases:
  - I-M1: `lineSubtotal + lineTax === lineTotal` for every line
  - I-M2: `Σ lineSubtotal === subtotal`, and the same for tax and grand total
  - I-M3: `marginAmount === subtotal − costTotal`
  - I-M4: no monetary result is ever `NaN` or infinite
- **Related fix, same body of work:** `buyer_quote_items.margin_percent` was
  `decimal(8,4)` (capped at ±9999.9999). `MarginConvention::marginPercent()` is
  unbounded below whenever `cost_price` exceeds the net sell price — a real
  data-entry mistake (e.g. cost keyed in rupiah against a price in thousands) —
  and PostgreSQL rejected the insert with a numeric field overflow (verified:
  `cost_price` 600,000 against `unit_price` 100 computes `margin_percent`
  `-599,900%%`, which does not fit `decimal(8,4)`). Migration
  `2026_07_29_150000_widen_margin_percent_on_buyer_quote_items_table` widens the
  column to `decimal(12,4)` without clamping the value.
- **Deliberately out of scope:** FX conversion (`exchange_rate` is
  `decimal(20,10)`; base-currency columns are still computed by the document
  models on top of `TotalsCollector`'s transaction-currency output — that carries
  its own rounding-policy question) and `SupplierOrder`/`SupplierOrderItem` (no
  line-item observer, no `line_subtotal`/`line_tax`/`cost_price` columns — a
  separate migration, unchanged by this work).

## Measured behaviour change

This is **not output-neutral**, and the divergence was measured (not estimated)
against the prior float implementation over a 16,800-field grid (10 prices × 6 tax
rates × 7 quantities × 2 scales × 2 price bases × taxable on/off) before the change
shipped:

| Path (`roundingScale`) | Fields | Differ from prior float output | Largest difference |
|---|---|---|---|
| Buyer quotes (`0`) | 8,400 | 2 (0.02%) | 1 rupiah |
| Supplier quotes (`4`) | 8,400 | 57 (0.68%) | 0.0001 |

Every difference is exactly one unit in the last place; none is larger. Invariant
I-M1 (`lineSubtotal + lineTax === lineTotal`) held in all 16,800 fields.

The mechanism was verified, not assumed: `0.3333 × 2.5` is exactly `0.83325`, a
tie that rounds up (`0.8333`). In binary floating point the *product* computes to
`0.83324999999999993516`, which sits below the tie, so `round()` faithfully rounds
the value it was handed *down* to `0.8332`. `round()` is not at fault — float
multiplication moved the product to the wrong side of the rounding boundary before
`round()` ever ran. In every differing case the exact value is the arithmetically
correct one and the old value was a float artefact.

Practical consequence: stored values do not move on deploy — line amounts
recalculate on save — so a historical document changes only if it is edited, and
then by at most one unit in the last place, always toward the correct rounding.

## Impact

- Affected specs: `erp-finance` (MODIFIED: Line Calculator, Totals Collector,
  Margin Convention)
- Affected code:
  - `app/Support/Money.php` (new)
  - `app/Services/Erp/Financial/LineCalculator.php`
  - `app/Services/Erp/Financial/TotalsCollector.php`
  - `app/Services/Erp/Financial/TotalsLine.php`
  - `app/Services/Erp/Financial/DocumentTotals.php`
  - `app/Services/Erp/Financial/MarginConvention.php` (unchanged formula; float
    boundary documented)
  - `app/Models/BuyerQuoteItem.php`, `app/Models/SupplierQuoteItem.php`,
    `app/Models/BuyerQuote.php`, `app/Models/SupplierQuote.php`
  - `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php`
  - `database/migrations/2026_07_29_150000_widen_margin_percent_on_buyer_quote_items_table.php`
  - `tests/ArchTest.php`, `tests/Feature/Erp/Financial/MoneyInvariantsTest.php`
- No new external dependency (`ext-bcmath` was already installed, `Dockerfile:54`).
- No new schema for the arithmetic itself (money columns were already
  `decimal(18,4)`); the only migration widens `margin_percent`'s column bound.

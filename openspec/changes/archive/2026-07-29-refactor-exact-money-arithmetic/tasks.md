# Tasks

## 1. Money value object
- [x] 1.1 `App\Support\Money` (`app/Support/Money.php`): immutable, integer minor units at scale 4, currency code; `zero()`, `ofMinorUnits()`, `fromDecimal()`, `plus()`, `minus()`, `multipliedBy()`, `dividedBy()`, `roundedToScale()`, `toDecimal()`, `toFloat()`, `compareTo()`, `isZero()`, `isNegative()`
- [x] 1.2 `Money::fromHighPrecision()` + `Money::roundDecimal()`: the single rounding boundary for callers doing chained bcmath arithmetic on `PRECISION` (20) scale decimal strings
- [x] 1.3 Currency-mismatch guard (`assertSameCurrency()`) throws `InvalidArgumentException` on `plus`/`minus`/`compareTo` across different currencies
- [x] 1.4 Half-away-from-zero rounding (`roundHalfAwayFromZero()`) matching PHP's `round()`

## 2. LineCalculator made exact
- [x] 2.1 `LineCalculator::calculate()` signature changed to `Money $unitPriceInput`, numeric-string `$taxRate`/`$quantity`, `int $roundingScale` (renamed from `currencyDecimals`, same position/values)
- [x] 2.2 All intermediates (`rawExcTax`, `rawTaxPerUnit`) computed as `Money::PRECISION`-scale decimal strings via `bcdiv`/`bcmul`/`bcsub`; no float division or multiplication anywhere in the method
- [x] 2.3 Each result crosses into `Money` exactly once via `Money::fromHighPrecision($decimal, $roundingScale, $currency)`
- [x] 2.4 Rejected the Money-as-intermediate draft after measuring 583/2,592 (22%) divergence from the float baseline; documented in the class docblock and in `design.md`
- [x] 2.5 Docblock corrected to state the true rounding order (unrounded intermediates through the multiply, round once after) and the measured non-identical-output fact (0.02%–1.5% of fields, one ULP each)

## 3. Call sites converted
- [x] 3.1 `BuyerQuoteItem::recalculatePrices()` — `Money::fromDecimal()` at the boundary, `roundingScale: 0`, `MarginConvention` called via `Money::toFloat()`
- [x] 3.2 `SupplierQuoteItem::calculateTotals()` — `roundingScale: 4`
- [x] 3.3 `BuyerQuotesRelationManager` two live-preview call sites (line-item child sync and item-totals preview) converted to `Money`
- [x] 3.4 `BuyerQuoteItem::collectTotals()`, `BuyerQuote::recalculateTotals()`, `SupplierQuote::recalculateTotals()` build `TotalsLine` with `Money` fields and pass the document's transaction currency

## 4. TotalsCollector, TotalsLine, DocumentTotals converted
- [x] 4.1 `TotalsLine`/`DocumentTotals` properties typed `Money` (quantity stays a numeric string — a count, not an amount)
- [x] 4.2 `TotalsCollector::collect()` sums via `Money::plus()` (integer addition of minor units), including `costTotal` via `costPrice->multipliedBy($quantity)`
- [x] 4.3 `marginPercent` still computed by `MarginConvention::marginPercent()` from `Money::toFloat()` inputs — the one documented float boundary

## 5. Guardrails
- [x] 5.1 `tests/ArchTest.php`: `financial services do not use floats` — bars `floatval`/`round` from `App\Services\Erp\Financial`, ignoring `MarginConvention`
- [x] 5.2 `tests/Feature/Erp/Financial/MoneyInvariantsTest.php`: I-M1–I-M4 property tests across both rounding scales, both price bases, taxable on/off
- [x] 5.3 Fixed a scientific-notation-reaching-bcmath bug surfaced by the invariant tests (quote preview path)

## 6. margin_percent overflow fix
- [x] 6.1 Migration `2026_07_29_150000_widen_margin_percent_on_buyer_quote_items_table`: `buyer_quote_items.margin_percent` `decimal(8,4)` → `decimal(12,4)`, value left unclamped
- [x] 6.2 Regression test in `tests/Feature/Erp/BuyerQuoteTest.php` proving a cost-above-price line (cost 600,000, unit price 100) saves and reads back `margin_percent` -599900.0 without overflowing

## 7. Quality gates
- [x] 7.1 `php vendor/bin/rector process` + `php vendor/bin/pint --dirty` on all changed files
- [x] 7.2 Existing financial tests pass with only type-shape assertion changes (e.g. `5200.0` → `'5200.0000'`); no amount moved
- [x] 7.3 `openspec validate refactor-exact-money-arithmetic --strict`

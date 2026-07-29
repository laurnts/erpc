## MODIFIED Requirements

### Requirement: Line Calculator
The system SHALL provide a `LineCalculator` service as the single source of truth
for all per-line financial arithmetic across the buyer-quote and supplier-quote
document types. To serve document types that assign different meanings to the
tax-inclusive flag, `LineCalculator` SHALL accept an explicit `priceBasis` (NET or
GROSS) and a `taxable` boolean, rather than a single overloaded `isTaxInclusive` flag.
Each model is responsible for mapping its own stored flags onto these parameters.

The mapping itself causes no behavioural change: holding rounding constant, the tax
arithmetic reproduces today's results exactly. A *separate* change in this same layer —
rounding to the document currency's precision (see the rounding scenario) — is an
intentional correction that WILL move some stored values and is applied to existing
rows by a one-time backfill. The two are distinct: the flag mapping preserves
semantics; the rounding precision fix is a deliberate, backfilled value change.

`LineCalculator::calculate()` takes `unitPriceInput` as `App\Support\Money` (an
exact, integer-minor-units amount) and `taxRate`/`quantity` as numeric decimal
strings — never a PHP `float` — and returns a `LineAmounts` value object of `Money`
fields. Every intermediate computed inside `calculate()` (the net-of-tax price, the
tax per unit, the post-multiply subtotal and tax) is a `bcmath` decimal string at
`Money::PRECISION` (20) scale; no step passes through binary floating-point
arithmetic. Each result value crosses from a high-precision decimal string into
`Money` exactly once, through `Money::fromHighPrecision()`, which applies
`$roundingScale` at that single boundary.

#### Scenario: Calculate line amounts from a NET price with tax applied on top
- **WHEN** `LineCalculator` receives `unitPriceInput` $5,200, `priceBasis` NET, `taxable` true, `taxRate` 11%, `quantity` 2
- **THEN** `unitPriceExcTax` is $5,200
- **AND** `taxAmountPerUnit` is $572
- **AND** `lineSubtotal` is $10,400
- **AND** `lineTax` is $1,144
- **AND** `lineTotal` is $11,544

#### Scenario: Calculate line amounts from a GROSS price with tax extracted
- **WHEN** `LineCalculator` receives `unitPriceInput` $5,772, `priceBasis` GROSS, `taxable` true, `taxRate` 11%, `quantity` 2
- **THEN** `unitPriceExcTax` is $5,200 ($5,772 / 1.11)
- **AND** `taxAmountPerUnit` is $572
- **AND** `lineSubtotal` is $10,400
- **AND** `lineTax` is $1,144
- **AND** `lineTotal` is $11,544

#### Scenario: Calculate line amounts for a non-taxable line
- **WHEN** `LineCalculator` receives `unitPriceInput` $5,200, `priceBasis` NET, `taxable` false
- **THEN** `unitPriceExcTax` is $5,200
- **AND** `taxAmountPerUnit` is $0
- **AND** `lineSubtotal` equals `lineTotal` ($10,400 for `quantity` 2)
- **AND** `lineTax` is $0

#### Scenario: Models map their own flags to priceBasis and taxable, preserving tax semantics
- **WHEN** a `BuyerQuoteItem` (whose `unit_price` is always net and whose `is_tax_inclusive` flag means "apply tax on top") is calculated
- **THEN** the model passes `priceBasis` NET and `taxable = is_tax_inclusive && taxRate > 0`
- **WHEN** a `SupplierQuoteItem` (whose `is_tax_inclusive` flag means "the entered price already includes tax") is calculated
- **THEN** the model passes `priceBasis = is_tax_inclusive ? GROSS : NET` and `taxable = supplier.is_taxable`
- **AND** holding rounding precision constant, the resulting `unit_price_exc_tax`, `line_subtotal`, `line_tax`, and `line_total` equal the pre-refactor computation for the same inputs (the mapping changes no tax math)

#### Scenario: Rounding-precision change is intentional and backfilled
- **WHEN** the new currency-precision rounding is applied to existing rows
- **THEN** IDR buyer quotes (currency precision 0, matching today's buyer 0-dp rounding) are unchanged — the common case moves nothing
- **AND** non-IDR buyer quotes (today forced to 0 dp) and all supplier quotes (today rounded to 4 dp) may have `line_subtotal`/`line_tax`/`line_total` adjusted to the correct currency precision
- **AND** a one-time backfill recomputes affected rows through `LineCalculator`
- **AND** regression tests assert the NEW currency-correct values, not the legacy ones

#### Scenario: Zero tax rate
- **WHEN** `LineCalculator` receives `taxRate` 0
- **THEN** `taxAmountPerUnit` is $0
- **AND** `lineSubtotal` equals `lineTotal`
- **AND** `unitPriceExcTax` equals `unitPriceInput` regardless of `priceBasis`

#### Scenario: Per-component rounding to currency precision (IDR)
- **WHEN** the document currency has 0 decimal places (e.g. IDR)
- **THEN** `lineSubtotal` and `lineTax` are each rounded to 0 decimal places first, via `Money::fromHighPrecision($decimal, roundingScale: 0, $currency)` acting on the unrounded high-precision intermediate
- **AND** `lineTotal` is derived as `roundedLineSubtotal + roundedLineTax` (an exact `Money::plus()`, integer addition of minor units) so that `lineSubtotal + lineTax === lineTotal` always holds exactly
- **AND** because each line satisfies this identity, the document-level sums also satisfy `subtotal + taxTotal === grandTotal` with no drift

#### Scenario: Arithmetic is exact — no binary float in the calculation path
- **WHEN** `LineCalculator::calculate()` computes the net-of-tax price, the tax amount, or either post-multiply line figure
- **THEN** every operation is `bcdiv`/`bcmul`/`bcsub` on decimal strings, never a native `+`/`-`/`*`/`/` on a PHP `float`
- **AND** the architecture test `financial services do not use floats` (`tests/ArchTest.php`) bars `floatval` and `round` from `App\Services\Erp\Financial`, exempting only `MarginConvention`
- **AND** `Money::toFloat()`'s only permitted callers in this layer are the `MarginConvention` call sites, which is the single documented crossing point from exact arithmetic to a float ratio

#### Scenario: Intermediates stay high-precision decimal strings, not Money, until the rounding boundary
- **WHEN** `LineCalculator` computes the net-of-tax unit price and then multiplies it by `quantity` to get the line subtotal
- **THEN** the per-unit intermediate is kept as an unrounded `Money::PRECISION` (20) scale decimal string, not converted to `Money` and back, until after the multiply
- **AND** an earlier draft that rounded the per-unit intermediate through `Money` (`dividedBy()` then `multipliedBy()`) before multiplying by quantity was measured to diverge from the prior float implementation in 583 of 2,592 fields (22%), because `Money` rounds to scale 4 on every operation and rounding a per-unit figure before scaling by quantity is not the same operation as rounding the scaled total
- **AND** each result crosses into `Money` exactly once, via `Money::fromHighPrecision()`, reproducing the prior float engine's "full precision through the multiply, round once after" ordering exactly

#### Scenario: Measured divergence from the prior float implementation is bounded to one unit in the last place
- **WHEN** the exact implementation is validated against the prior float implementation over a 16,800-field grid (10 prices × 6 tax rates × 7 quantities × 2 rounding scales × 2 price bases × taxable on/off)
- **THEN** the buyer-quote path (`roundingScale: 0`) differs in 2 of 8,400 fields (0.02%), largest difference 1 rupiah
- **AND** the supplier-quote path (`roundingScale: 4`) differs in 57 of 8,400 fields (0.68%), largest difference 0.0001
- **AND** every differing field differs by exactly one unit in the last place; none differs by more
- **AND** in every differing case the exact value is the arithmetically correct rounding and the prior float value was a float artefact (e.g. `0.3333 × 2.5` is exactly the tie `0.83325`, which rounds up to `0.8333`, but the binary-float product computes to `0.83324999999999993516` — below the tie — so the prior `round()` call faithfully rounded that wrong value down to `0.8332`)
- **AND** stored values do not move on deploy — line amounts recalculate only on save — so an existing document's figures change only if the line is edited, and then by at most this one-unit-in-the-last-place correction

#### Scenario: LineCalculator runs only in the line-item model observer for persistence
- **WHEN** a `BuyerQuoteItem` or `SupplierQuoteItem` is created or updated
- **THEN** its line-item persist hook (`BuyerQuoteItemObserver`; the equivalent `SupplierQuoteItemObserver` or model save hook for supplier-quote items) calls `LineCalculator` exactly once on save
- **AND** the resulting `unitPriceExcTax`, `lineSubtotal`, `lineTax`, `lineTotal`, and (for buyer items) `margin_percent` are persisted to the database
- **AND** the persisted columns are the single canonical record; PDF generators, Blade views, and resource tables read those columns and perform no tax or total arithmetic of their own
- **NOTE** `SupplierOrderItem` is out of scope for this change (it has no line-item observer, lacks `line_subtotal`/`line_tax`/`cost_price` columns, and carries base-currency totals); migrating it is a separate change

#### Scenario: LineCalculator may be called read-only for live form display
- **WHEN** a Filament form needs to show a live total or margin preview as the user edits, before the record is saved
- **THEN** the form callback MAY call `LineCalculator` (and `MarginConvention`) to compute display-only values for the on-screen preview
- **AND** these display values are never the canonical persisted figures
- **AND** on save the line-item observer re-runs `LineCalculator` and persists the definitive values, which take precedence over any form-preview value
- **AND** there is still exactly one arithmetic implementation (`LineCalculator`); the form preview and the observer call the same function, so no second formula can diverge

#### Scenario: No independent arithmetic in views or PDF generators
- **WHEN** a buyer quote PDF is generated
- **THEN** the service reads `line_subtotal`, `line_tax`, `line_total` from the stored item columns
- **AND** the document footer total is read from the stored `BuyerQuote::total` column
- **AND** no margin, tax, or total formula is re-implemented in the PDF service or Blade template

#### Scenario: Hidden-item value redistribution is a presentation transform that preserves the stored total
- **WHEN** a buyer quote PDF distributes the value of `hide_from_pdf` items across the visible lines
- **THEN** the redistribution is a presentation-only transform of how the already-stored total is shown across visible rows
- **AND** the sum of the displayed visible-line values equals the stored `BuyerQuote::total` exactly
- **AND** the redistribution never changes, recomputes, or rounds the document total itself
- **AND** this is the single permitted arithmetic exception in a PDF generator, and it is bounded to redistribution of stored values, not tax/margin computation

---

### Requirement: Totals Collector
The system SHALL provide a `TotalsCollector` service that aggregates a pre-filtered
collection of document lines into document-level totals **in the document's
transaction currency**. `TotalsCollector` is FX-agnostic: any base-currency
conversion (e.g. `base_subtotal`, `base_total` via `exchange_rate` on supplier
documents) is performed by the document model on the totals `TotalsCollector` returns,
not inside `TotalsCollector`. Margin outputs (`marginAmount`, `marginPercent`) are
meaningful only for buyer documents, which carry a cost-vs-sell relationship; supplier
documents consume only `subtotal`, `taxTotal`, and `grandTotal` and ignore the margin
fields.

`TotalsLine` and `DocumentTotals` hold `Money` for every monetary field
(`lineSubtotal`, `lineTax`, `lineTotal`, `costPrice`/`costTotal`, `subtotal`,
`taxTotal`, `grandTotal`, `marginAmount`); `quantity` stays a numeric decimal
string, since a count is not an amount. Summation is exact `Money` addition
(`Money::plus()`, integer addition of minor units at scale 4) — never a float
`+` — so a document with a hundred repeating-decimal lines totals to the same
figure a person gets with a calculator, with no accumulated binary-float drift.
`marginPercent` remains the one float in `DocumentTotals`: it is produced by
`MarginConvention::marginPercent()` from `costTotal.toFloat()` and
`subtotal.toFloat()`, the single documented crossing point from exact `Money`
arithmetic to a float ratio.

#### Scenario: Collect totals from main lines
- **WHEN** `TotalsCollector` receives a collection of main-item lines with `lineSubtotal`, `lineTax`, `lineTotal`, `costPrice`, `quantity`
- **THEN** `subtotal` is the sum of all `lineSubtotal` values
- **AND** `taxTotal` is the sum of all `lineTax` values
- **AND** `grandTotal` is the sum of all `lineTotal` values
- **AND** `costTotal` is the sum of all `costPrice × quantity` values
- **AND** `marginAmount` is `subtotal − costTotal`
- **AND** `marginPercent` is computed via `MarginConvention::marginPercent(costTotal, subtotal)`

#### Scenario: Caller is responsible for filtering child items
- **WHEN** a service request document has both main and child line items
- **THEN** the caller filters lines to main items only before passing to `TotalsCollector`
- **AND** `TotalsCollector` does not apply any parent_id filter internally
- **AND** the resulting `grandTotal` reflects main items only, matching the stored document total

#### Scenario: TotalsCollector runs only in the model observer and PNL approval
- **WHEN** a line item is saved and its document totals need updating
- **THEN** the line item observer triggers `TotalsCollector` on the parent document
- **AND** the resulting `subtotal`, `taxTotal`, `grandTotal`, `marginAmount`, `marginPercent` are persisted to the document row
- **AND** PDF generation, P&L views, and all Filament resources read from those stored document columns
- **AND** no view or PDF generator calls `TotalsCollector` directly

#### Scenario: Empty line collection
- **WHEN** `TotalsCollector` receives an empty collection
- **THEN** all totals are zero
- **AND** `marginPercent` is zero

#### Scenario: Summation is exact Money addition, immune to float accumulation
- **WHEN** `TotalsCollector::collect()` sums `lineSubtotal`, `lineTax`, `lineTotal`, and `costPrice × quantity` across many lines
- **THEN** each running total is accumulated via `Money::plus()` (integer addition of minor units at scale 4), never a float `+`
- **AND** for any collection of lines, `Σ lineSubtotal === subtotal`, `Σ lineTax === taxTotal`, and `Σ lineTotal === grandTotal` exactly, with no accumulated rounding drift regardless of collection size
- **AND** this invariant (I-M2) is locked by `tests/Feature/Erp/Financial/MoneyInvariantsTest.php` across both rounding scales (0 and 4)

---

### Requirement: Margin Convention
The system SHALL define a single canonical margin convention used by all calculation
paths, document generators, form callbacks, and displays. `marginPercent` is
deliberately a `float`, not `Money`: it is a ratio, not a monetary amount, and
carries no minor units to be exact about. `MarginConvention`'s two inputs
(`cost`, `sellNet`) are plain floats obtained by calling `Money::toFloat()` at the
call site — the one documented, intentional point where an exact `Money` amount
is allowed to cross into a float, immediately before it is consumed by a ratio
calculation and not fed back into any further monetary arithmetic.

#### Scenario: Margin percentage definition (on-selling)
- **WHEN** a buyer quote item has `cost_price` $4,600 and `unit_price_exc_tax` $5,200
- **THEN** `MarginConvention::marginPercent(cost, sellNet)` returns 11.54%
- **AND** the formula is `(sellNet − cost) / sellNet × 100`
- **AND** no other margin formula is used anywhere in the codebase

#### Scenario: Net unit price from cost and target margin
- **WHEN** `cost_price` is $4,600 and target margin is 11.5385% (the exact margin for a $5,200 sell)
- **THEN** `MarginConvention::netUnitPrice(cost, marginPercent)` returns $5,200 (`4,600 / (1 − 0.115385)`)
- **AND** the formula is `cost / (1 − marginPercent / 100)`
- **NOTE** at the rounded 11.54% the result is ~$5,199.55; the inverse round-trips exactly only at full precision

#### Scenario: Margin stored consistently
- **WHEN** a `BuyerQuoteItem` is saved
- **THEN** `margin_percent` is computed by `MarginConvention::marginPercent(cost_price, unit_price_exc_tax)`, with both inputs obtained via `Money::toFloat()` on the exact amounts computed by `LineCalculator`
- **AND** `getDisplayMarginPercent()` and `calculatedMarginPercent` return the same on-selling value
- **AND** `BuyerQuote::total_margin_percent` aggregates using the same formula

#### Scenario: MarginConvention is the only margin formula
- **WHEN** a margin percentage or a cost-to-selling-price conversion is computed anywhere in the system
- **THEN** the computation calls `MarginConvention` — never an inline `(sell − cost) / sell` or `cost * (1 + m)` or `cost / (1 − m)` formula written elsewhere
- **AND** for persisted figures the call chain is observer → `LineCalculator`/`TotalsCollector` (exact `Money` arithmetic) → `MarginConvention` (float ratio, via `Money::toFloat()`) → stored column
- **AND** Blade views and PDF generators do not call `MarginConvention` at all; they read the stored `margin_percent` column
- **AND** Filament form callbacks MAY call `MarginConvention` read-only for live display and for wizard prefill, because doing so reuses the single formula rather than duplicating it

#### Scenario: Wizard prefill derives selling price from cost and margin
- **WHEN** the buyer-quote wizard prefills net unit price from a supplier quote cost and the team default margin
- **THEN** the selling price is set as `MarginConvention::netUnitPrice(cost, defaultMarginPercent)` (`cost / (1 − defaultMarginPercent / 100)`)
- **AND** this value is written to the form field as a prefill default
- **AND** when the form is saved, the line-item observer runs `LineCalculator` and persists the definitive stored values
- **AND** the consolidated creation path, per-supplier path, and manual fallback path all call `MarginConvention::netUnitPrice` and therefore produce the same unit price for identical inputs

#### Scenario: Guard against division by zero
- **WHEN** `MarginConvention::marginPercent(cost, sellNet)` is called with `sellNet` equal to 0
- **THEN** it returns 0.0 rather than dividing by zero
- **WHEN** `MarginConvention::netUnitPrice(cost, marginPercent)` is called with `marginPercent` at or above 100
- **THEN** it returns 0.0 (price collapse is treated as an invalid margin and surfaced as zero, matching existing behaviour) rather than dividing by zero or returning a negative price
- **AND** these guard branches are covered by unit tests

#### Scenario: margin_percent storage was widened to hold a legitimate extreme value
- **WHEN** `cost_price` exceeds the net selling price by a large amount (e.g. cost keyed in rupiah against a price meant to be in thousands)
- **THEN** `MarginConvention::marginPercent()` is unbounded below and can legitimately return a value with a magnitude in the hundreds of thousands of percent (verified: `cost_price` 600,000 against `unit_price_exc_tax` 100 on quantity 1 computes `margin_percent` -599900.0)
- **AND** `buyer_quote_items.margin_percent`, originally `decimal(8,4)` (capped at ±9999.9999), is widened to `decimal(12,4)` by migration `2026_07_29_150000_widen_margin_percent_on_buyer_quote_items_table` so this value is stored, not clamped, truncated, or hidden — a large negative margin is a data-entry signal the user needs to see
- **AND** `margin_amount` (`decimal(18,4)`) already had enough headroom and was not changed
- **AND** `supplier_quote_items` has no `margin_percent`/`margin_amount` column and needed no equivalent change

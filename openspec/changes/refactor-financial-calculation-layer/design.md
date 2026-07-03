# Design: Financial Calculation Layer

## Core Principle: Calculate Once on Write, Read Everywhere Else

**There is exactly one place in the system where financial arithmetic happens:
the model observer that fires when a line item is saved.**

Everything else — form displays, PDFs, P&L views, PNL sign-off, exports — is a
**reader** of pre-computed, persisted values. No view, controller, relation
manager, or PDF generator may re-implement a tax/margin/total *formula*. They may
call the single calculation function read-only for a live preview (see nuance below),
and a PDF may apply the one bounded presentation transform documented later
(hidden-item redistribution that preserves the stored total) — but no second formula
exists anywhere.

```
User saves / edits a line item
        │
        ▼
  Observer fires (creating / updating)
        │
        ▼
  LineCalculator.calculate()          ← THE SINGLE CALCULATION
        │
        ▼
  Persists unit_price_exc_tax,        ← stored, not re-derived
          line_subtotal,
          line_tax,
          line_total,
          margin_percent
        │
        ▼
  TotalsCollector.collect()           ← runs once on the document
        │
        ▼
  Persists subtotal,                  ← stored, not re-derived
          tax_total,
          total,
          margin_percent on document

  ───────────────────────────────────
  Everything below here is READ-ONLY

  Form display              → reads stored columns
  Buyer Quote PDF           → reads stored columns
  Buyer Order PDF           → reads stored columns
  P&L view / PDF            → reads stored columns (or FinancialSnapshot when approved)
  Customer portal quote RM  → reads stored columns (second display path, portal panel)
  PNL approval snapshot     → reads stored document totals, freezes them
```

**Why this matters:** the audit trail for every approved number traces back to
a single deterministic path. A director approving a PNL can know that the margin
they see was computed by the same function that computed the margin in the form,
the PDF, and the supplier-order totals — because there is only one function.

**One nuance — live form preview.** Filament wizards show totals/margin as the user
types, before save. That live preview is *display only*, but it must still come from
the same `LineCalculator`/`MarginConvention` — called read-only, not from a second
inline formula. The invariant is "one calculation *function*", not "calculation only
ever runs in the observer". The observer remains the sole owner of *persisted* values;
the form may call the same function to render an unsaved preview. This is what keeps
the wizard usable (Problem: stripping all form arithmetic would freeze live totals)
without reintroducing a divergent second formula (Problem: a second formula is the
root cause this change eliminates).

---

## Context

Every commercial document in this ERP (SupplierQuote, BuyerQuote, SupplierOrder,
BuyerOrder, ProfitAndLoss) answers the same questions per line:
- What is the net unit price (ex-tax)?
- What is the tax amount?
- What is the gross total (inc-tax)?
- What is the margin?

And per document:
- What is the net subtotal (main items only)?
- What is the tax total?
- What is the grand total?
- What is the blended margin?

Currently each document type answers these independently with slightly different
formulas. This design establishes the single, canonical answer for each.

---

## Decision: Margin convention is markup-on-selling (margin-on-revenue)

The existing `erp-quoting` Margin Analysis spec scenario defines:
> gross_margin $1,217 ÷ selling price $9,840 = 12.4%

This is **margin-on-selling** (also called gross margin %):

```
margin% = (sell_net − cost) / sell_net × 100
```

All generation paths, live form recalculations, stored `margin_percent` columns,
and display accessors SHALL use this formula exclusively. The alternative
(markup-on-cost) is discarded.

**Rationale:** margin-on-selling is the standard gross profit margin definition
used in accounting. The existing spec already chose it. The code diverged during
sprint pressure.

---

## Decision: P&L sell base is net (line_subtotal), never gross (line_total)

`line_subtotal = quantity × unit_price_exc_tax` — this is net revenue.
`line_total = line_subtotal + line_tax` — this includes collected VAT.

Margin = revenue − cost. VAT is not revenue; it is collected on behalf of the
government. The P&L SHALL always use `line_subtotal` as the sell base.

---

## Decision: "Main items only" is enforced at the collection boundary

The `TotalsCollector` accepts a pre-filtered line set. The caller is responsible
for passing only main items (`parent_id === null` for service requests). This
makes the rule explicit and testable at the seam.

The helper `BuyerQuoteItem::filterForServiceTotals()` already exists and is used
correctly in PO generation. All other aggregation sites (PDFs, P&L views,
`recalculateTotals`) SHALL call it or an equivalent before collecting totals.

---

## Decision: Financial figures are snapshotted at approval, not resolved live

When a PNL is approved, `TotalsCollector::collect()` runs once over the linked
buyer quote's main items and the resulting `FinancialSnapshot` is stored on the
PNL row. Views and PDFs render from the snapshot; they do not re-resolve the
buyer quote or re-sum line items.

**The write-on-render bug is eliminated for *all* states, not just approved.**
`resolveSourceBuyerQuote()` today performs a `saveQuietly()` during render
(re-pointing `buyer_quote_id` to the latest quote). That write is removed entirely:

- Approved PNL → read `financial_snapshot`; `resolveSourceBuyerQuote()` is not called.
- Unapproved PNL → resolve the source buyer quote **read-only** (no `saveQuietly`,
  no re-pointing as a side effect) and render live totals via `TotalsCollector`.

Rendering any PNL, in any state, must cause zero database writes. Re-linking a PNL to
a different buyer quote, if ever needed, becomes an explicit user action — never an
invisible consequence of opening the page.

**Trade-off:** if the buyer quote is legitimately revised after approval (via the
existing `createNewVersion()` flow), the PNL must be reset to `NEED_APPROVAL` and
a new snapshot taken when re-approved. This already happens via
`resetPnlStatusForRequest()` — the snapshot integrates naturally with the existing
re-approval path.

---

## Component Specifications

### LineCalculator

Location: `app/Services/Erp/Financial/LineCalculator.php`
Pattern: `final readonly class` with no framework dependencies (pure PHP).

**Critical design decision — explicit price basis, not an overloaded flag.**
The two document families assign *opposite meanings* to `is_tax_inclusive`:

- `SupplierQuoteItem`: `is_tax_inclusive = true` means the entered unit price is
  **gross** (tax must be extracted: `net = gross / (1 + rate)`).
- `BuyerQuoteItem`: `unit_price` is **always net**; `is_tax_inclusive = true` is an
  *apply-tax-on-top toggle* (`is_tax_inclusive = false` means no tax at all).

A single `isTaxInclusive` parameter cannot model both without silently changing buyer
numbers. Therefore `LineCalculator` takes an explicit `priceBasis` enum (`NET` |
`GROSS`) plus a `taxable` boolean. Each model maps its own flags; **no stored value
changes.**

Inputs per call:
- `unitPriceInput` — the price as entered
- `priceBasis` — `PriceBasis::NET` (price is tax-exclusive) or `PriceBasis::GROSS` (price includes tax)
- `taxable` — bool (whether any tax applies to this line)
- `taxRate` — Percentage (0–100)
- `quantity`

Model → parameter mapping (this mapping is the *only* place the flags are interpreted):

| Model | `priceBasis` | `taxable` |
|---|---|---|
| `BuyerQuoteItem` | always `NET` | `is_tax_inclusive && taxRate > 0` |
| `SupplierQuoteItem` | `is_tax_inclusive ? GROSS : NET` | `supplier.is_taxable` |
| `SupplierOrderItem` | mirror the supplier-quote mapping it derives from | `supplier.is_taxable` |

Outputs (value object `LineAmounts`):
- `unitPriceExcTax` — net unit price
- `taxAmountPerUnit`
- `lineSubtotal` — net line total (`unitPriceExcTax × quantity`)
- `lineTax` — tax line total
- `lineTotal` — gross line total

**Rounding — per component, then derive (this is the drift fix, not its cause).**
Round `lineSubtotal` and `lineTax` each to the document currency's precision (IDR = 0
dp) **first**, then set `lineTotal = roundedLineSubtotal + roundedLineTax`. Because
every line satisfies `subtotal + tax === total` exactly, the document-level sums
(`Σsubtotal + Σtax === Σtotal`) also hold with no drift. (An earlier draft said "round
once on lineTotal" — that is wrong: leaving the components fractional reintroduces the
exact document-level drift this refactor exists to eliminate.)

**`currencyDecimals` resolution.** The line item has no direct currency; the observer
resolves precision by walking `item → parent document (BuyerQuote / SupplierQuote) →
currency.decimal_places`. Fallback when the currency is null (drafts, legacy rows):
default to `2` (the `Currency` model default). This fallback choice is significant —
it determines which existing rows the backfill touches (see "Stored-value impact").

**Stored-value impact (must be owned, not hidden).** This rounding rule is a *change*
from today: `BuyerQuoteItem` currently rounds every component to 0 dp regardless of
currency; `SupplierQuoteItem` rounds to 4 dp. So:
- IDR buyer quotes (precision 0 = today's buyer 0 dp) → **no change** (the dominant case).
- Non-IDR buyer quotes (today forced 0 dp) and all supplier quotes (today 4 dp) → some
  `line_subtotal`/`line_tax`/`line_total` values move to correct currency precision.
This is an intentional correctness fix. It requires a one-time backfill (recompute
affected rows via `LineCalculator`) and regression tests that assert the NEW values.
The flag-mapping (priceBasis/taxable) by itself changes nothing; only this rounding
precision change moves values.

### TotalsCollector

Location: `app/Services/Erp/Financial/TotalsCollector.php`
Pattern: `final readonly class`.

Accepts a `Collection` of already-filtered lines (main items only for service
requests). Each line must provide `lineSubtotal`, `lineTax`, `lineTotal`,
`costPrice`, `quantity`.

Outputs (value object `DocumentTotals`):
- `subtotal` — sum of `lineSubtotal`
- `taxTotal` — sum of `lineTax`
- `grandTotal` — sum of `lineTotal`
- `costTotal` — sum of `costPrice × quantity`
- `marginAmount` — `subtotal − costTotal`
- `marginPercent` — `MarginConvention::marginPercent(costTotal, subtotal)`

### MarginConvention

Location: `app/Services/Erp/Financial/MarginConvention.php`
Pattern: `final class` with only static methods (no state).

```
marginPercent(cost, sell_net) = (sell_net − cost) / sell_net × 100
netUnitPrice(cost, marginPercent) = cost / (1 − marginPercent / 100)
```

These two methods are the only permitted definition of margin in the codebase.

### FinancialSnapshot (Spatie Data object)

Location: `app/Data/Erp/FinancialSnapshot.php`
Pattern: `final class` extending `Spatie\LaravelData\Data`.

Fields (all stored as strings to match `decimal:4` columns):
- `subtotal`, `taxTotal`, `grandTotal`, `costTotal`
- `marginAmount`, `marginPercent`
- `currency` (ISO code)
- `snapshotAt` (Carbon)
- `buyerQuoteId` — the quote ID at snapshot time (audit reference)

Stored as a JSON cast on `profit_and_losses.financial_snapshot` (new nullable
column). When non-null, views and PDFs read from it exclusively.

---

## What is no longer permitted

The following patterns are explicitly prohibited after this refactor:

| Prohibited | Replace with |
|---|---|
| A *second* tax/margin/total formula written inline anywhere (the root cause) | The single `LineCalculator` / `MarginConvention` implementation, called from wherever the value is needed |
| Inline margin formula (`cost * (1 + m/100)`, `cost / (1 - m/100)`) hand-written outside `MarginConvention` | `MarginConvention::netUnitPrice()` / `marginPercent()` |
| A Filament form callback **persisting** canonical line/total values | The line-item observer persists on save; the form may only set prefill defaults and read-only previews |
| `getEffectiveLineTotal()` (gross) used as a margin/sell base in a view or PDF | Read `line_subtotal` (net) from the stored column |
| `sum('line_subtotal')` re-summed in a Blade template for the document total | Read the stored document `subtotal` / `total` column |
| `resolveSourceBuyerQuote()` performing a write (`saveQuietly`) during render | Resolve read-only; for approved PNLs read `financial_snapshot`; never persist as a render side effect |

**Permitted (not prohibited):**

- A Filament form callback calling `LineCalculator`/`MarginConvention` **read-only** to
  render a live, unsaved preview, and to prefill the wizard selling price from cost +
  default margin. This reuses the single function, so no divergent formula is created.
- The buyer-quote PDF redistributing `hide_from_pdf` item value across visible lines,
  **provided** the displayed visible-line values sum to the stored `BuyerQuote::total`
  and the total itself is never recomputed. This is a bounded presentation transform,
  not a financial calculation.

The only legitimate call sites that **persist** `LineCalculator` output are the
line-item model observers (`BuyerQuoteItemObserver`, `SupplierQuoteItemObserver`,
`SupplierOrderItemObserver`). The only legitimate sites that **persist** `TotalsCollector`
output are those observers' parent-total recalculation and the PNL approval action.
`MarginConvention` and `LineCalculator` may additionally be called read-only for display,
but their *persisted* result has exactly one origin.

---

## Migration Path

The refactor is additive. Existing `recalculatePrices()` / `recalculateTotals()`
observer hooks remain in place but are rewritten to delegate to `LineCalculator`
and `TotalsCollector`. Filament form `afterStateUpdated` callbacks that currently
perform inline arithmetic are removed — the observer fires on save and the
updated stored values are reflected on the next form load.

Only one schema change:
- `profit_and_losses`: add nullable `financial_snapshot` (JSON) column

The backfill is **data only** (recompute existing rows) — it adds no columns, so the
"one schema change" statement holds. SupplierOrder migration (which WOULD need new
columns) is deliberately out of scope (see Files to touch).

No breaking API changes (internal only, no public API on these models today).

**Two intentional value changes (own them, don't hide them):**

1. **Line totals at correct currency precision.** As described under LineCalculator
   "Stored-value impact": non-IDR buyer quotes and all supplier quotes (today 4 dp) get
   `line_subtotal`/`line_tax`/`line_total` re-rounded. IDR buyer quotes are unchanged.
2. **Margin figures.** `BuyerQuote::totalMarginAmount` currently sums per-unit
   `margin_amount` ignoring quantity, and `totalMarginPercent` uses markup-on-cost.
   `TotalsCollector` computes `marginAmount = subtotal − costTotal` (quantity-correct)
   and on-selling percent. So `margin_amount`/`margin_percent` shift for multi-quantity
   or previously-on-cost lines.

Both are correctness fixes, not regressions. A single one-off backfill command recomputes
`line_subtotal`/`line_tax`/`line_total`/`margin_*` on existing buyer and supplier quote
items via the new layer. Regression tests MUST assert the *new* expected numbers, not
the current stored ones.

---

## Files to touch (implementation phase)

| New | `app/Services/Erp/Financial/LineCalculator.php` |
| New | `app/Services/Erp/Financial/TotalsCollector.php` |
| New | `app/Services/Erp/Financial/MarginConvention.php` |
| New | `app/Enums/Erp/PriceBasis.php` — `NET` \| `GROSS` enum for `LineCalculator` |
| New | `app/Data/Erp/FinancialSnapshot.php` |
| New | migration `add_financial_snapshot_to_profit_and_losses_table` |
| Modify | `app/Models/BuyerQuoteItem.php` — `recalculatePrices()` delegates to `LineCalculator` (maps NET + `taxable`); `getDisplayMarginPercent()` → `MarginConvention`; `getEffectiveLineTax()`/`getEffectiveLineTotal()` read stored columns (drop inline `* taxRate / 100`); `createFromSupplierQuoteItem()` markup → `MarginConvention::netUnitPrice` |
| Modify | `app/Observers/BuyerQuoteItemObserver.php` — confirm `recalculatePrices()`/`recalculateTotals()` remain the only persist path after form callbacks are stripped |
| Modify | `app/Models/SupplierQuoteItem.php` — `calculateTotals()` delegates to `LineCalculator` (maps `is_tax_inclusive ? GROSS : NET`) |
| Modify | `app/Observers/SupplierQuoteItemObserver.php` (or model save hook) — same invariant as buyer item observer |
| Modify | `app/Models/BuyerQuote.php` — `recalculateTotals()` → `TotalsCollector` (filter children first); `createNewVersion()` margin → `MarginConvention` |
| Modify | `app/Models/SupplierQuote.php` — `recalculateTotals()` → `TotalsCollector` (transaction currency; model applies FX to base_* after) |
| Modify | `app/Models/ProfitAndLoss.php` — snapshot at approval; `resolveSourceBuyerQuote()` made read-only (no `saveQuietly`) |
| Modify | `app/Services/Erp/PdfGenerationService.php` — filter children; read stored totals; redistribution preserves stored total; use snapshot |
| Modify | `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php` — strip persisted-value callbacks; live preview/prefill call the layer read-only |
| Modify | Blade views for P&L — use snapshot; use net sell (`line_subtotal`) for margin base |
| New | backfill command/migration — recompute `line_subtotal`/`line_tax`/`line_total` on existing buyer & supplier quote items at correct currency precision |
| Verify (no change) | `app/Filament/Customer/Resources/CustomerRequestResource/RelationManagers/BuyerQuotesRelationManager.php` — second buyer-quote display path; confirm it stays a pure reader |
| Out of scope (follow-up) | `SupplierOrderItem` / `SupplierOrder` — no line-item observer, no `line_subtotal`/`line_tax`/`cost_price` columns, base-currency FX totals; migrating them is a separate change |
| New | `tests/Unit/Erp/Financial/LineCalculatorTest.php` (both bases + non-taxable + per-component rounding) |
| New | `tests/Unit/Erp/Financial/TotalsCollectorTest.php` |
| New | `tests/Unit/Erp/Financial/MarginConventionTest.php` (incl. div-by-zero guards) |
| New | `tests/Feature/Erp/ProfitAndLossSnapshotTest.php` |
| New | `tests/Feature/Erp/DocumentPdfTotalsTest.php` — buyer-quote & buyer-order PDF grand total === stored `$document->total` for service requests with children |

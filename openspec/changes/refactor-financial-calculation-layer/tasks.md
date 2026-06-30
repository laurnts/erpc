# Tasks: refactor-financial-calculation-layer

Ordered for safe incremental delivery. Each task is independently verifiable.
Parallelisable groups are marked **(parallel)**.

---

## Phase 1 — Foundation services (no existing code touched)

- [x] 0. Create `app/Enums/Erp/PriceBasis.php`
  - String-backed enum: `NET` (entered price is tax-exclusive), `GROSS` (entered price includes tax)

- [x] 1. Create `app/Services/Erp/Financial/LineCalculator.php`
  - `final readonly class`; pure PHP; no framework dependencies
  - Method: `calculate(float $unitPriceInput, PriceBasis $priceBasis, bool $taxable, float $taxRate, float $quantity, int $currencyDecimals): LineAmounts`
  - **Do NOT use a single `isTaxInclusive` flag** — buyer and supplier items give that flag opposite meanings (see design.md "explicit price basis"). The model maps its own flags to `priceBasis` + `taxable`.
  - GROSS basis: `unitPriceExcTax = unitPriceInput / (1 + rate/100)`. NET basis: `unitPriceExcTax = unitPriceInput`.
  - `taxable === false` ⇒ `lineTax = 0`, `lineTotal = lineSubtotal`.
  - **Rounding (drift fix):** round `lineSubtotal` and `lineTax` each to `currencyDecimals` first, then `lineTotal = roundedLineSubtotal + roundedLineTax`. Guarantees `subtotal + tax === total` per line and, by summation, per document. Do NOT leave components fractional and round only the total.
  - **`currencyDecimals` source:** the caller (line-item observer) resolves it via `item → parent document → currency.decimal_places`; fallback `2` when currency is null. This is a CHANGE from today (buyer 0 dp / supplier 4 dp regardless of currency) and moves some stored values — see C-1 / task 9c backfill.

- [x] 2. Create `app/Services/Erp/Financial/MarginConvention.php`
  - `final class`; static methods only
  - `marginPercent(cost, sellNet): float` — `(sellNet − cost) / sellNet × 100`
  - `netUnitPrice(cost, marginPercent): float` — `cost / (1 − marginPercent / 100)`
  - Guard: return `0.0` when `sellNet <= 0.0` (marginPercent) and when `marginPercent >= 100.0` (netUnitPrice). Use `<=`/`>=`, not float `===`, so near-boundary values (99.999) are handled. Cover both guards with unit tests.

- [x] 3. Create `app/Services/Erp/Financial/TotalsCollector.php`
  - `final readonly class`
  - Accepts `Collection` of objects exposing `lineSubtotal`, `lineTax`, `lineTotal`, `costPrice`, `quantity`
  - Returns `DocumentTotals` value object; calls `MarginConvention::marginPercent`

- [x] 4. Create `app/Data/Erp/FinancialSnapshot.php`
  - Extends `Spatie\LaravelData\Data`; `final class`
  - Fields: `subtotal`, `taxTotal`, `grandTotal`, `costTotal`, `marginAmount`, `marginPercent`, `currency`, `snapshotAt` (Carbon), `buyerQuoteId` (int)

- [x] 5. Write unit tests **(parallel with task 1–4)**
  - `tests/Unit/Erp/Financial/LineCalculatorTest.php` — all scenarios from spec
  - `tests/Unit/Erp/Financial/MarginConventionTest.php` — on-selling formula, edge cases (zero cost, zero sell)
  - `tests/Unit/Erp/Financial/TotalsCollectorTest.php` — aggregate correctness, empty collection

---

## Phase 2 — Model observer refactor

> NOTE: the persist trigger is the **line-item** observer, not the parent quote
> observer. `BuyerQuoteItem` is `#[ObservedBy(BuyerQuoteItemObserver::class)]`, which
> already fires `recalculatePrices()` (creating/updating) and `recalculateTotals()`
> (created/updated/deleted). Confirm the equivalent `SupplierQuoteItemObserver` /
> `SupplierOrderItemObserver` seams exist; if `SupplierOrderItem` has none, add one.
> The "calculation only in the observer" invariant is therefore already achievable —
> these tasks just point the existing observer methods at the new layer.

- [ ] 6. Refactor `BuyerQuoteItem::recalculatePrices()` to delegate to `LineCalculator`
  - Map flags: `priceBasis = PriceBasis::NET`, `taxable = is_tax_inclusive && tax_rate > 0` (preserves current buyer semantics — see C1)
  - Resolve `LineCalculator` from the container in `BuyerQuoteItemObserver`
  - Replace all inline `round(qty * price, 0)` arithmetic
  - Replace `getDisplayMarginPercent()` and `calculatedMarginPercent` to call `MarginConvention::marginPercent`
  - **Expect `margin_amount`/`margin_percent` to change value** (qty-correct + on-selling) — see I4; assert NEW numbers in tests, not current stored ones

- [ ] 7. Refactor `SupplierQuoteItem::calculateTotals()` to delegate to `LineCalculator`
  - Map flags: `priceBasis = is_tax_inclusive ? PriceBasis::GROSS : PriceBasis::NET`, `taxable = supplier.is_taxable` (preserves current supplier semantics — see C1)
  - Verify via `SupplierQuoteItemObserver` (or the model save hook, if no observer exists) that this is the only persist path
  - NOTE supplier rounding moves from 4 dp to currency precision (C-1) — intentional; covered by backfill (task 9c) and updated assertions

- [ ] 8. Refactor `BuyerQuote::recalculateTotals()` to delegate to `TotalsCollector`
  - Apply `BuyerQuoteItem::filterForServiceTotals()` before passing lines
  - Verify stored `total` matches `TotalsCollector` output on existing IDR data (margin and non-IDR/supplier line values will differ — expected, see C-1 / I4)

- [ ] 9. Refactor `SupplierQuote::recalculateTotals()` to delegate to `TotalsCollector`
  - `TotalsCollector` returns transaction-currency totals only; the model applies `exchange_rate` to populate `base_subtotal`/`base_tax_total`/`base_total` afterward (TotalsCollector is FX-agnostic — I-4)
  - Margin fields from `TotalsCollector` are ignored for supplier documents

- [ ] 9c. Backfill command — recompute existing rows at correct precision (C-1 / I4)
  - Recompute `line_subtotal`/`line_tax`/`line_total`/`margin_*` on existing `BuyerQuoteItem` and `SupplierQuoteItem` rows via `LineCalculator`/`MarginConvention`
  - Re-run parent `recalculateTotals()` so document totals stay consistent
  - Idempotent; safe to re-run

> **SupplierOrder / SupplierOrderItem are OUT OF SCOPE** (was tasks 7b/9b): no line-item
> observer, no `line_subtotal`/`line_tax`/`cost_price` columns, and base-currency FX
> totals that `TotalsCollector` does not model. Migrating them needs new columns + a new
> observer + FX handling — a separate change. Removing them here keeps "one schema change".

- [ ] 10. Run full test suite; confirm no regressions except the intentional value changes (C-1 line precision, I4 margins), which must have updated assertions

---

## Phase 3 — Eliminate the *second formula* in the UI layer

The principle (C2): there must be exactly one arithmetic *function*. Form callbacks may
still compute live previews and prefill defaults, but they must do so by calling the
single `LineCalculator`/`MarginConvention` **read-only** — never by re-implementing the
formula inline. The line-item observer remains the sole owner of *persisted* values.

> Depends on Phase 2 (tasks 6–9b): do NOT strip a form callback until the observer
> provably owns every value that callback was writing, or that value is lost. This is a
> hard ordering dependency, not just a dependency on task 2.

- [ ] 11. Replace inline formulas in `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php` (~2,808 lines; line refs verified post-portal-commit) with read-only calls to the layer
  - Wizard prefill, margin-on-selling: line 1178 (`round($costPrice / (1 - $defaultMarginPercent / 100), 0)`) and line 1214 (child) — replace inline formula with `MarginConvention::netUnitPrice()` (prefill default)
  - Wizard fallback, margin-on-cost (to be eliminated): line 1668 (`round($costPrice * (1 + $defaultMarginPercent / 100), 4)`) and line 1586 — replace with `MarginConvention::netUnitPrice()` (kills the on-cost divergence)
  - **Line 699** (`$unitPriceExcTax = round($costPrice * (1 + $marginPercent / 100), 0)` inside the margin `afterStateUpdated`) — additional markup-ON-COST site; replace with `MarginConvention::netUnitPrice()`
  - `calculateItemTotals` / `afterStateUpdated` live-preview callbacks: rewrite to call `LineCalculator` read-only for the on-screen preview instead of inline arithmetic — keep the live preview working, but with zero second formula
  - `resolveChildItemTaxSettings` and `convertMargin` inline margin writes: route through `MarginConvention`
  - Result: the form shows live totals/margin via the same function the observer uses; the observer still owns all persisted values

- [ ] 11b. Remove remaining inline formulas in `app/Models/BuyerQuoteItem.php` and `app/Models/BuyerQuote.php` (I-1 — these survive outside the relation manager)
  - `getEffectiveLineTax()` (~L352) and `getEffectiveLineTotal()` (~L399) do inline `round(lineSubtotal * taxRate / 100, 0)` — rewrite to read the stored `line_tax`/`line_total` columns (the observer already persists them)
  - `createFromSupplierQuoteItem()` (~L301) uses markup-on-cost `costPrice * (1 + markupPercent/100)` — route through `MarginConvention::netUnitPrice()`
  - `BuyerQuote::createNewVersion()` (~L493) computes margin inline (bypasses `MarginConvention`) — route through `MarginConvention`
  - These violate "MarginConvention is the only margin formula" and are not in the relation manager, so the task-11 grep would miss them

- [ ] 12. Verify: (a) after saving any buyer quote item via the form, the stored columns match `LineCalculator` output exactly (not a form-preview value); (b) the live wizard preview still updates as margin/qty/price change and matches the post-save stored values; (c) grep confirms no remaining inline `(1 + .../100)`, `(1 - .../100)`, or `* tax_rate / 100` formula in the relation manager, `BuyerQuoteItem`, or `BuyerQuote`

---

## Phase 4 — Make PDFs and views pure readers

No financial *formula* in this phase. These files become readers of stored columns.
The one bounded exception is documented in task 13 (hidden-item redistribution).

- [ ] 13. Fix `PdfGenerationService::generateBuyerQuotePdf`
  - Load main items only (apply `BuyerQuoteItem::filterForServiceTotals`) for the line listing
  - Source the grand total footer from stored `$quote->total` — no summing
  - **Hidden-item redistribution (I3):** spreading `hide_from_pdf` item value across visible lines is a *presentation transform*, not a financial calculation. It is the single permitted arithmetic exception. Constraint: the sum of displayed visible-line values MUST equal stored `$quote->total` exactly, and the total itself is never recomputed/re-rounded. Add a test asserting the displayed lines sum to the stored total.
  - Remove every tax/margin formula and every total-recompute from this file; the only arithmetic left is the bounded redistribution above

- [ ] 14. Fix `PdfGenerationService::generateBuyerOrderPdf` — same pattern (read stored total; bounded redistribution only)

- [ ] 15b. Extract `ProfitAndLoss::groupedSupplierItems(): Collection` **first** (the views in task 15 call it)
  - Encapsulates the load + group pipeline; both views call it
  - Returns pre-grouped data with stored column values; no calculations inside

- [ ] 15. Fix P&L Blade views — remove all formulas (do after 15b so views call the helper)
  - `profit-and-loss.blade.php`: `supplierTotal` reads `line_subtotal` (stored column), not `getEffectiveLineTotal()`. `supplierCostTotal` reads `cost_price × quantity` — the only permitted arithmetic in views, as `cost_price` and `quantity` are stored atomic values
  - `pnl-selected-items.blade.php` — same; remove `$itemMargin` computation; color driven from stored `margin_percent`
  - No `round()`, no summing of tax+subtotal, no margin division in either template

- [ ] 16b. Verify the customer portal buyer-quote display path stays a pure reader
  - `app/Filament/Customer/Resources/CustomerRequestResource/RelationManagers/BuyerQuotesRelationManager.php` is the SECOND buyer-quote display surface (added by portal commit)
  - It already reads `$record->total` / `$record->currency` with no summing or margin/tax arithmetic — already compliant
  - No refactor needed today; this task just enforces the read-only invariant on both display paths as the layer lands

- [ ] 16c. Feature test `tests/Feature/Erp/DocumentPdfTotalsTest.php` (covers Problem #2 / success criterion)
  - For a service request with main + child items, assert the generated buyer-quote PDF grand total equals stored `BuyerQuote::total`
  - Assert the displayed visible-line values sum to the stored total (redistribution invariant)
  - Same assertions for the buyer-order PDF

---

## Phase 5 — Financial snapshot

- [ ] 17. Add migration: `add_financial_snapshot_to_profit_and_losses_table`
  - Nullable JSON column `financial_snapshot`
  - Cast on model: `FinancialSnapshot::class`

- [ ] 18. Add snapshot write to PNL approval path
  - When all three approver timestamps are set, call `TotalsCollector::collect()` on the linked buyer quote's filtered main items
  - Store as `financial_snapshot`

- [ ] 19. Make PNL render write-free in ALL states; read snapshot when approved (I2)
  - Approved (`financial_snapshot` non-null): read totals from the snapshot; do NOT call `resolveSourceBuyerQuote()` or query `buyer_quote_items`
  - Unapproved: resolve the source buyer quote **read-only** — remove the `saveQuietly()`/re-pointing side effect from `resolveSourceBuyerQuote()` entirely; render live totals via `TotalsCollector`
  - Net invariant: opening a PNL page in any state causes zero DB writes. Add a test asserting no writes on render for both approved and unapproved PNLs.

- [ ] 20. Update `BuyerQuote::createNewVersion()` path to clear `financial_snapshot` on PNL reset
  - Already calls `resetPnlStatusForRequest()` — extend to null the snapshot column

- [ ] 21. Feature test: `tests/Feature/Erp/ProfitAndLossSnapshotTest.php`
  - Snapshot stored on approval
  - Snapshot survives buyer quote soft-delete (figures unchanged)
  - No write on render
  - Snapshot cleared on version reset

---

## Phase 6 — Prepayment sync

- [ ] 22. EXTEND the existing `app/Observers/BuyerQuoteObserver.php`
  - NOTE: this file now EXISTS (portal commit 723ed66) and is already registered via `#[ObservedBy(BuyerQuoteObserver::class)]` on `BuyerQuote` — do NOT re-register
  - PRESERVE both existing hooks: `creating()` (team_id/creator_id/quote_number/valid_until defaults) and `updated()` (portal SENT-status notification via `NotifyPortalUsers` + `PortalBuyerQuoteSentNotification`)
  - Add the prepayment PERCENT/FIXED sync call into the existing `creating()` hook
  - ADD a new `updating()` hook for the sync (it does not exist yet)
  - Mirror `SupplierQuoteObserver::syncPrepaymentColumns()` (private method ~line 149, called from `creating()` and `updating()`)

- [ ] 23. Test: PERCENT prepayment displays correctly after single-supplier create, consolidated create, and edit

---

## Phase 7 — Validation and cleanup

- [ ] 24. Fix `validatePaymentTermsTotal` single-term skip (now IN scope — see proposal note)
  - Remove `if ($termCount <= 1) { return; }` guard
  - Validate: `prepayment (if PERCENT) + sum(term%) === 100` always
  - Add a test: single-term quote with prepayment + one term not summing to 100 is rejected; valid case passes

- [ ] 25. Remove dead `accept` action from `BuyerQuotesRelationManager` (`->visible()` always false)

- [ ] 26. Clean up unused imports and replace inline FQCNs in `BuyerQuotesRelationManager` and `AcceptanceReportResource`

- [ ] 27. Run `vendor/bin/pint --dirty`

- [ ] 28. Run full test suite; confirm ≥80% coverage on new service classes

---

## Dependencies and notes

- Tasks 0–5 have no dependencies on existing code; they can start immediately
- Tasks 6–9 depend on 0–3 completing first (need `PriceBasis`, `LineCalculator`, `MarginConvention`, `TotalsCollector`)
- Task 9c (backfill) depends on 6–9 (the new calculation must exist before recomputing rows)
- **Tasks 11–11b–12 depend on 6–9, not just task 2.** Stripping a form callback before the observer owns that value would lose it. This is a hard ordering dependency.
- Tasks 13–16c depend on 8 (TotalsCollector in BuyerQuote); task 15 depends on 15b (helper extracted first)
- Tasks 17–21 depend on 3–4 (TotalsCollector + FinancialSnapshot)
- Task 22 is independent of all other phases
- Tasks 23–28 are independent cleanup; do last
- **Intentional value changes:** (C-1) line totals re-round to currency precision for non-IDR buyer + all supplier quotes; (I4) margin amount/percent shift to qty-correct + on-selling. IDR buyer quotes are unchanged. All affected tests assert the NEW numbers; task 9c backfills existing rows.
- **SupplierOrder / SupplierOrderItem are explicitly out of scope** (no observer, no columns, FX) — a separate follow-up change.

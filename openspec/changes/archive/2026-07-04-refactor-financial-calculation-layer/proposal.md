# Proposal: Refactor Financial Calculation Layer

## Change ID
`refactor-financial-calculation-layer`

## Problem

A deep audit of the procurement pipeline revealed that financial calculations —
price, tax, margin, and document totals — are implemented independently in at
least seven places, with no shared contract. The practical consequences are:

1. **VAT counted as profit.** The P&L subtracts net cost from gross (tax-inclusive)
   sell, inflating the headline margin figure Directors sign off on by the full
   VAT amount on every tax-inclusive line.

2. **Customer PDFs overstate the price.** `PdfGenerationService` sums all persisted
   line items including service child/detail rows. The stored quote total (used for
   margin and PO generation) excludes children. The document sent to the customer
   disagrees with the system's own records.

3. **Two margin formulas coexist.** The generation wizard uses markup-on-selling
   (`cost / (1 − margin%)`); the manual fallback and live form recalculation use
   markup-on-cost (`cost × (1 + margin%)`). The existing `erp-quoting` Margin
   Analysis spec defines one formula (on-selling, 12.4% = 1217/9840) — the code
   violates it in half the paths.

4. **Approved PNL figures are not frozen.** `resolveSourceBuyerQuote()` re-points an
   approved PNL to a different (possibly draft) buyer quote on the next page render,
   with no re-approval and no audit trail.

5. **Rounding drift.** Subtotal, tax, and total are each rounded independently to 0
   decimals, so `subtotal + tax_total ≠ total` is common on printed documents.

These are not isolated bugs. They are symptoms of a missing architectural layer:
a canonical calculation contract that all document types use.

## Proposed Change

Introduce a **Financial Calculation Layer** — a small set of pure, framework-free
services that own the single definition of every financial operation — and migrate
all existing calculation sites to call it.

The layer consists of four components:

| Component | Responsibility |
|---|---|
| `LineCalculator` | Tax and line-total arithmetic for a single document line. Takes an explicit `priceBasis` (NET/GROSS) + `taxable` flag so buyer and supplier items (which assign opposite meanings to `is_tax_inclusive`) share one engine without changing any stored value. |
| `TotalsCollector` | Aggregates a filtered set of lines into document totals |
| `MarginConvention` | Single canonical margin formula (on-selling, per existing spec) |
| `FinancialSnapshot` | Immutable record of calculated totals at approval time |

The in-scope document types (`SupplierQuote`, `BuyerQuote`, `ProfitAndLoss`) delegate to
these services instead of computing inline. `SupplierOrder`/`SupplierOrderItem` are
deliberately excluded (no line-item observer, missing `line_subtotal`/`line_tax`/
`cost_price` columns, and base-currency FX totals the collector does not model) — see
Out of scope.

## Scope

### Spec deltas in this change

> Note: both `erp-finance` and `erp-quoting` are **existing** specs. The `erp-finance`
> deltas ADD new requirements to an existing spec (not a new spec), and crucially
> REMOVE the old `Tax Calculation Service` and MODIFY `Profit & Loss Calculation` so the
> new layer is the single source of truth rather than a competing one.

| Spec | Delta type | Capability |
|---|---|---|
| `erp-finance` | ADDED | Line Calculator (explicit `priceBasis`/`taxable`), Totals Collector, Margin Convention |
| `erp-finance` | ADDED | Financial Snapshot on approval |
| `erp-finance` | ADDED | Prepayment Field Synchronisation |
| `erp-finance` | MODIFIED | Profit & Loss Calculation — margin uses net sell base, not gross total |
| `erp-finance` | REMOVED | Tax Calculation Service — superseded by Line Calculator (no two competing sources of truth) |
| `erp-quoting` | MODIFIED | Margin Analysis — canonicalize on-selling formula |
| `erp-quoting` | MODIFIED | Profit and Loss Document — add snapshot requirement |
| `erp-quoting` | MODIFIED | Buyer Quotes — prepayment sync (all 10 prior scenarios preserved) |

### Customer portal impact (commit 723ed66, merged after this proposal was drafted)

The customer portal introduces **no new financial calculation site**. Its only
financial display surface,
`app/Filament/Customer/Resources/CustomerRequestResource/RelationManagers/BuyerQuotesRelationManager.php`,
is already a pure reader — it renders `number_format((float) $record->total, 2)`
from the stored column and performs no tax/margin/sum arithmetic. The customer
`ShipmentsRelationManager`, `PortalContext`, `CustomerRequestStagePresenter`,
portal widgets and actions contain no financial arithmetic (verified by grep
across `app/Filament/Customer`, `app/Services/CustomerPortal`,
`app/Actions/CustomerPortal`). The portal therefore becomes a second consumer of
the single calculation source — reinforcing the case for this refactor — without
adding a path that needs migrating.

Note also: `app/Observers/BuyerQuoteObserver.php` now exists (created by the portal
commit) with `creating()` and `updated()` hooks. Phase 6 must EXTEND it, not create it.

### In scope (small, directly related to the calculation/validation surface)

- Payment-terms single-term validation gap (task 24) — the prepayment percentage is part
  of the same terms-sum-to-100 invariant this change canonicalises; fixing it here with a
  test is cheaper than a separate PR.

### Out of scope (separate change/PRs)

- **Supplier order calculation migration** — `SupplierOrder`/`SupplierOrderItem` need a
  new line-item observer, new `line_subtotal`/`line_tax`/`cost_price` columns, and FX
  (base-currency) handling that `TotalsCollector` does not model. A separate change.
- Request/PO number generation race condition (lockForUpdate)
- QE reset on supplier quote edit
- Service child item FK cascade on delete
- Supplier order transaction wrapper

## Success Criteria

- One `LineCalculator` implementation; no second tax formula anywhere in scope (incl. `getEffectiveLineTax/Total`, `createFromSupplierQuoteItem`, `createNewVersion`)
- One `MarginConvention` definition; all margin callers (incl. read-only previews) use it
- Buyer-quote and buyer-order PDFs: grand total matches stored `$document->total` (incl. service requests with children)
- P&L sell total uses `line_subtotal` (net); no `line_total` (gross) in margin math
- An approved PNL stores a `FinancialSnapshot`; its figures do not change after approval; rendering any PNL causes zero DB writes
- Line values round to the document currency's precision; a backfill applies this to existing rows (IDR buyer quotes unchanged); regression tests assert the new values
- All existing tests pass (with updated assertions for the intentional value changes); new Pest unit tests cover every `LineCalculator` scenario

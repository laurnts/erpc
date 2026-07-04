## ADDED Requirements

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
- **THEN** `lineSubtotal` and `lineTax` are each rounded to 0 decimal places first
- **AND** `lineTotal` is derived as `roundedLineSubtotal + roundedLineTax` so that `lineSubtotal + lineTax === lineTotal` always holds exactly
- **AND** because each line satisfies this identity, the document-level sums also satisfy `subtotal + taxTotal === grandTotal` with no drift

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

---

### Requirement: Margin Convention
The system SHALL define a single canonical margin convention used by all calculation
paths, document generators, form callbacks, and displays.

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
- **THEN** `margin_percent` is computed by `MarginConvention::marginPercent(cost_price, unit_price_exc_tax)`
- **AND** `getDisplayMarginPercent()` and `calculatedMarginPercent` return the same on-selling value
- **AND** `BuyerQuote::total_margin_percent` aggregates using the same formula

#### Scenario: MarginConvention is the only margin formula
- **WHEN** a margin percentage or a cost-to-selling-price conversion is computed anywhere in the system
- **THEN** the computation calls `MarginConvention` — never an inline `(sell − cost) / sell` or `cost * (1 + m)` or `cost / (1 − m)` formula written elsewhere
- **AND** for persisted figures the call chain is observer → `LineCalculator`/`TotalsCollector` → `MarginConvention` → stored column
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

---

### Requirement: Financial Snapshot
The system SHALL freeze the financial figures of an approved Profit and Loss
document as an immutable snapshot at the moment of approval.

#### Scenario: Snapshot is created on PNL approval
- **WHEN** a `ProfitAndLoss` document is approved (all three approver timestamps set)
- **THEN** `TotalsCollector` runs over the linked buyer quote's main items
- **AND** the resulting totals are stored as `financial_snapshot` on the PNL record
- **AND** the snapshot includes: `subtotal`, `taxTotal`, `grandTotal`, `costTotal`, `marginAmount`, `marginPercent`, `currency`, `snapshotAt`, `buyerQuoteId`

#### Scenario: PNL views read from snapshot
- **WHEN** an approved PNL is rendered (view page or PDF)
- **AND** `financial_snapshot` is non-null
- **THEN** all totals and margin figures are read from the snapshot
- **AND** no live query is issued against `buyer_quote_items` for financial figures

#### Scenario: Snapshot survives buyer quote soft-delete
- **WHEN** the source buyer quote is soft-deleted after a PNL is approved
- **THEN** the PNL's displayed totals remain unchanged (read from snapshot)
- **AND** `resolveSourceBuyerQuote()` is NOT called during view/PDF rendering

#### Scenario: Snapshot is invalidated on version change
- **WHEN** a new buyer quote version is created via `createNewVersion()`
- **THEN** the PNL status resets to `NEED_APPROVAL`
- **AND** `financial_snapshot` is cleared to null
- **AND** a new snapshot is taken when the PNL is re-approved

#### Scenario: Unapproved PNL has no snapshot
- **WHEN** a PNL is in `NEED_APPROVAL` or `PENDING` status
- **THEN** `financial_snapshot` is null
- **AND** the view renders live-calculated totals from the linked buyer quote

---

### Requirement: Prepayment Field Synchronisation
The system SHALL ensure that the buyer quote prepayment amount and percentage
columns remain consistent regardless of the creation or edit path used.

#### Scenario: Percent-type prepayment stored correctly
- **WHEN** a buyer quote is saved with `prepayment_type` PERCENT and `prepayment_amount` 30
- **THEN** `prepayment_percent` is set to 30
- **AND** `prepayment_amount` is set to 0
- **AND** `ViewRequest::getPrepaymentDisplay()` returns "30%"

#### Scenario: Fixed-type prepayment stored correctly
- **WHEN** a buyer quote is saved with `prepayment_type` FIXED and `prepayment_amount` 5000000
- **THEN** `prepayment_percent` is set to 0
- **AND** `prepayment_amount` is set to 5000000
- **AND** `ViewRequest::getPrepaymentDisplay()` returns the formatted fixed amount

#### Scenario: Sync applies on all creation paths
- **WHEN** a buyer quote is created via single-supplier, multi-supplier consolidated, or manual entry
- **THEN** the prepayment sync runs in all cases
- **AND** `prepayment_percent` is never left at its default 0 for a PERCENT-type quote

#### Scenario: Sync applies on edit
- **WHEN** an existing buyer quote's prepayment is edited
- **THEN** the sync runs and both columns are updated consistently

## MODIFIED Requirements

### Requirement: Profit & Loss Calculation
The system SHALL calculate per-request P&L in base currency, using the net sell
subtotal (tax-exclusive) as the revenue base for margin, never the tax-inclusive
grand total.

#### Scenario: Calculate buyer revenue base
- **WHEN** a buyer order exists
- **THEN** the gross buyer total (for invoicing and cash flow) is `order.total`
- **AND** the margin revenue base is the net sell subtotal (`order` subtotal, tax-exclusive)
- **WHEN** no order exists
- **THEN** both figures derive from the latest applicable buyer quote: gross from `buyer_quote.total`, net revenue base from `buyer_quote.subtotal`

#### Scenario: Calculate supplier cost
- **WHEN** request has supplier orders
- **THEN** supplier_cost is sum of all supplier_order.total_in_base

#### Scenario: Calculate gross margin from net revenue, not gross total
- **WHEN** the net sell subtotal (tax-exclusive) is $9,840 and supplier_cost is $8,623
- **THEN** gross_margin is $1,217 ($9,840 − $8,623)
- **AND** margin_percent is 12.4% (`(9,840 − 8,623) / 9,840 × 100`, via `MarginConvention`)
- **AND** collected VAT is excluded from the revenue base, so it never inflates the margin

#### Scenario: Track cash flow
- **WHEN** viewing P&L
- **THEN** collected shows sum of buyer payments received
- **AND** paid_out shows sum of supplier payments made
- **AND** net_cash_flow is collected - paid_out

#### Scenario: Display P&L on request
- **WHEN** viewing request Invoices tab
- **THEN** P&L summary is displayed
- **AND** shows revenue, costs, profit, margin, collections, payments

## REMOVED Requirements

### Requirement: Tax Calculation Service
**Reason:** Superseded by the `Line Calculator` requirement in this change. The
`TaxCalculationService` described per-line tax arithmetic (tax-exclusive/inclusive
unit price, line totals, zero-tax handling, tax-rate snapshot) that is now owned by
`LineCalculator` as the single source of truth. Retaining both would assert two
competing single-sources-of-truth for the same arithmetic, defeating the purpose of
this change. The tax-rate snapshot behaviour (snapshot `tax_rate` from `tax_code.rate`
on save, immune to later rate changes) is preserved and continues to be enforced by
the line-item observers feeding `LineCalculator`.

**Migration:** Any code referencing `TaxCalculationService` is migrated to call
`LineCalculator` via the line-item model observers. No data migration is required —
stored column values are unchanged.

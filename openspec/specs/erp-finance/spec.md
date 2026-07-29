# erp-finance Specification

## Purpose
TBD - created by archiving change add-erp-trading-system. Update Purpose after archive.
## Requirements
### Requirement: Buyer Invoices
The system SHALL manage buyer invoices with line items, supporting prepayment, balance, and credit note types.

#### Scenario: Create prepayment invoice
- **WHEN** buyer order has 30% prepayment term
- **THEN** prepayment invoice is created with type "prepayment"
- **AND** amount is 30% of order total (including tax)
- **AND** due_at equals issued_at (immediate payment)

#### Scenario: Create balance invoice
- **WHEN** goods are delivered
- **THEN** balance invoice is created with type "balance"
- **AND** amount is remaining 70% of order total
- **AND** due_at is delivery_date + net_days

#### Scenario: Invoice with line items
- **WHEN** buyer invoice is created from order
- **THEN** invoice items are created linking to buyer_order_items
- **AND** each item includes description, quantity, unit, tax fields
- **AND** invoice totals are calculated from item totals

#### Scenario: Partial invoicing
- **WHEN** only some order items are invoiced
- **THEN** invoice contains only selected items
- **AND** additional invoices can be created for remaining items
- **AND** sum of all invoice items cannot exceed order quantities

#### Scenario: A newly created invoice carries no number
- **WHEN** a buyer invoice is created (directly, or via `BuyerInvoice::issueFromOrder()`'s initial draft)
- **THEN** `invoice_number` is `NULL`
- **AND** any number of unnumbered draft invoices can coexist in the same team (the `(team_id, invoice_number)` unique index treats `NULL` as distinct)

#### Scenario: Invoice numbering is assigned at issue, not at creation
- **WHEN** a draft invoice transitions to "sent" via `markAsSent()`
- **THEN** `invoice_number` is allocated at that point in the format `{prefix}-{year}-{sequence:04d}` (e.g. "INV-2026-0001"), using the team's configured invoice prefix and the current calendar year
- **AND** a discarded draft that is deleted before being sent never consumes a number

#### Scenario: Re-issuing an invoice does not renumber it
- **WHEN** `markAsSent()` or `assignNumberIfMissing()` is called on an invoice that already carries a number
- **THEN** the existing `invoice_number` is left unchanged

#### Scenario: Invoice status tracking
- **WHEN** invoice is created
- **THEN** status defaults to "draft"
- **AND** status can progress: draft → sent → partial → paid → overdue → cancelled
- **AND** issued_at is set when status becomes "sent"

#### Scenario: Calculate overdue status
- **WHEN** due_at has passed and status is not "paid"
- **THEN** status automatically changes to "overdue"
- **AND** days_overdue is calculated

### Requirement: Buyer Credit Notes
The system SHALL support credit notes as a special invoice type with negative amounts.

#### Scenario: Create credit note from invoice
- **WHEN** admin creates credit note for invoice INV-2024-0089-1
- **THEN** type is set to "credit_note"
- **AND** original_invoice_id links to the original invoice
- **AND** credit_reason is required

#### Scenario: Credit note items
- **WHEN** credit note is created
- **THEN** items can be selected from original invoice items
- **AND** quantities are negative
- **AND** amounts are negative

#### Scenario: Credit note affects balance
- **WHEN** credit note is created for $500 against $3,000 invoice
- **THEN** buyer's outstanding balance decreases by $500
- **AND** P&L reflects the adjustment

#### Scenario: Debit note for additional charges
- **WHEN** admin creates debit note for additional charges
- **THEN** type is set to "debit_note"
- **AND** amounts are positive
- **AND** increases buyer's outstanding balance

---

### Requirement: Buyer Payments
The system SHALL record buyer payments with required proof uploads.

#### Scenario: Record full payment
- **WHEN** admin records payment of $3,277 for invoice INV-2024-0089-1
- **THEN** payment is linked to the invoice
- **AND** method, reference, and paid_at are recorded
- **AND** recorded_by captures the user

#### Scenario: Record partial payment
- **WHEN** admin records payment of $2,000 against $3,277 invoice
- **THEN** invoice status changes to "partial"
- **AND** amount_outstanding is calculated as $1,277

#### Scenario: Payment completes invoice
- **WHEN** total payments equal or exceed invoice amount
- **THEN** invoice status changes to "paid"
- **AND** paid_at is set to date of final payment

#### Scenario: Payment in different currency
- **WHEN** payment received in EUR for USD invoice
- **THEN** original_amount and original_currency are recorded
- **AND** exchange_rate used for conversion is stored
- **AND** amount reflects converted value in invoice currency

#### Scenario: Proof upload required
- **WHEN** recording a payment
- **THEN** attachment upload is required (payment_proof type)
- **AND** attachment is linked to the payment record

---

### Requirement: Supplier Invoices
The system SHALL manage multiple supplier invoices per request with line items, multi-currency support, and credit note handling.

#### Scenario: Record supplier invoice
- **WHEN** admin records invoice MC-INV-2024-892 from MotorCorp
- **THEN** invoice is linked to request and supplier_order
- **AND** amount in supplier's currency is stored

#### Scenario: Supplier invoice with line items
- **WHEN** supplier invoice is recorded
- **THEN** invoice items are created linking to supplier_order_items
- **AND** each item includes description, quantity, unit, tax fields
- **AND** invoice totals are calculated from item totals

#### Scenario: Verify supplier invoice against order
- **WHEN** reviewing supplier invoice items
- **THEN** quantities can be compared against order quantities
- **AND** prices can be compared against quoted prices
- **AND** discrepancies are highlighted

#### Scenario: Exchange rate snapshot
- **WHEN** supplier invoice is recorded in IDR
- **THEN** exchange_rate_to_base is captured
- **AND** amount_in_base is calculated for reporting

#### Scenario: Supplier invoice status
- **WHEN** supplier invoice is created
- **THEN** status defaults to "received"
- **AND** status can progress: received → approved → paid → disputed

#### Scenario: Multiple supplier invoices per request
- **WHEN** request has 3 suppliers
- **THEN** each can have separate invoice(s)
- **AND** total supplier cost is sum of all invoice amounts_in_base

---

### Requirement: Supplier Credit Notes
The system SHALL support credit notes from suppliers as a special invoice type.

#### Scenario: Record supplier credit note
- **WHEN** admin records credit note from supplier
- **THEN** type is set to "credit_note"
- **AND** original_invoice_id links to the original supplier invoice
- **AND** credit_reason is recorded

#### Scenario: Supplier credit note items
- **WHEN** supplier credit note is recorded
- **THEN** items reference original invoice items
- **AND** quantities and amounts are negative
- **AND** affects supplier cost calculations

---

### Requirement: Supplier Payments
The system SHALL record supplier payments with proof uploads.

#### Scenario: Record supplier payment
- **WHEN** admin records payment of Rp 83,250,000 to MotorCorp
- **THEN** payment is linked to supplier_invoice
- **AND** proof attachment is required

#### Scenario: Payment in different currency
- **WHEN** paying IDR invoice with USD
- **THEN** original_amount (USD) and exchange_rate are stored
- **AND** amount reflects value in invoice currency

#### Scenario: Supplier payment completes invoice
- **WHEN** total payments equal invoice amount
- **THEN** supplier_invoice status changes to "paid"
- **AND** paid_at is set on the invoice

---

### Requirement: File Attachments via Media Library
The system SHALL use Spatie Media Library for file attachments on ERP entities.

#### Scenario: Upload payment proof
- **WHEN** recording a payment
- **THEN** file is uploaded via `$payment->addMedia($file)->toMediaCollection('payment_proof')`
- **AND** file metadata (name, size, mime type) is stored in `media` table

#### Scenario: Upload shipping documents
- **WHEN** recording shipment
- **THEN** multiple files can be uploaded to collections: `shipping_doc`, `pod`
- **AND** `$shipment->getMedia('shipping_doc')` returns all documents

#### Scenario: View attachments
- **WHEN** viewing a payment or shipment
- **THEN** all media items are listed with download URLs via `getUrl()`
- **AND** uploader tracked via media custom properties if needed

#### Scenario: Media collections defined
- **WHEN** ERP models implement `HasMedia`
- **THEN** collections are defined: `payment_proof`, `shipping_doc`, `pod`, `quote_doc`, `invoice_copy`

---

### Requirement: Activity Logging
The system SHALL log all significant activities on a request for audit trail.

#### Scenario: Log stage change
- **WHEN** request stage changes from "new" to "sourcing"
- **THEN** activity is logged with type "stage_change"
- **AND** properties include old_stage and new_stage

#### Scenario: Log quote extension
- **WHEN** buyer quote is extended
- **THEN** activity is logged with type "buyer_quote_extended"
- **AND** properties include dates, reason, and flags

#### Scenario: Log payment received
- **WHEN** buyer payment is recorded
- **THEN** activity is logged with type "payment_received"
- **AND** properties include amount, method, reference

#### Scenario: Log shipment update
- **WHEN** shipment status changes
- **THEN** activity is logged with type "shipment_update"
- **AND** properties include old_status and new_status

#### Scenario: View activity timeline
- **WHEN** viewing request Activity Log tab
- **THEN** all activities are shown in reverse chronological order
- **AND** grouped by date with user and timestamp

---

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

### Requirement: Financial Document Delete Protection
The system SHALL enforce database-level `RESTRICT` (not `CASCADE`) on the foreign keys running
from finance-side documents back to the request or company they document: `buyer_invoices.request_id`,
`buyer_payments.buyer_invoice_id`, `supplier_invoices.request_id`, `supplier_invoices.supplier_id`,
`supplier_payments.supplier_invoice_id`, and `profit_and_losses.request_id`. A request or company
that has produced any of these documents SHALL NOT be hard-deletable; it must be archived
(soft-deleted) instead. Each table's `team_id` foreign key is unaffected and remains
`cascadeOnDelete()`.

#### Scenario: Force-deleting a request with a buyer invoice is rejected
- **WHEN** `Request::withTrashed()->forceDelete()` is called on a request that has a `BuyerInvoice`
- **THEN** the database raises a foreign key violation
- **AND** the request row still exists afterward

#### Scenario: Force-deleting a company is blocked by its request's buyer invoice
- **WHEN** `Company::withTrashed()->forceDelete()` is called on a buyer company whose request has
  a `BuyerInvoice`
- **THEN** the delete cascades from the company to its request (the `requests.buyer_id` foreign
  key is unchanged and still cascades)
- **AND** is then rejected at the request, because `buyer_invoices.request_id` now restricts
- **AND** neither the company nor the request row is removed

#### Scenario: A RESTRICT violation aborts the enclosing transaction
- **WHEN** a force-delete is blocked by one of these RESTRICT constraints while running inside a
  database transaction
- **THEN** PostgreSQL marks that transaction as aborted
- **AND** code that needs to keep querying afterward (e.g. to assert the row survived) must run
  the force-delete inside its own savepoint (`DB::transaction()`), not directly in the caller's
  outer transaction

#### Scenario: A request with no financial documents can still be force-deleted
- **WHEN** a request with no buyer/supplier orders, invoices, payments, or profit-and-loss record
  is force-deleted
- **THEN** the delete succeeds and the row is removed

### Requirement: Document Number Allocation
Buyer invoice, buyer payment, supplier invoice, and supplier payment numbers SHALL be allocated from a locked counter row (one row per team, document key, and calendar year in `document_number_sequences`) rather than by reading the highest existing number, so that concurrent creates cannot receive the same number and the sequence does not regress once a team's yearly count for a document type passes 9999 (a string-sorted "highest number" query would otherwise rank `'9999'` above `'10000'`). Numbers are strictly monotonic per (team, key, year): a rolled-back or deleted document permanently skips its number rather than having that number reissued to a later document.

#### Scenario: Concurrent invoice creates do not collide
- **WHEN** two buyer invoices are issued for the same team in the same year at effectively the same time
- **THEN** each receives a distinct, correctly incrementing `invoice_number`
- **AND** neither create fails due to a duplicate-number save

#### Scenario: Sequence does not regress past 9999
- **WHEN** a team's buyer payment count for a year is already at 9999
- **THEN** the next allocated sequence value is 10000, not a value already issued

#### Scenario: Deleting an invoice does not free its number for reissue
- **WHEN** a numbered buyer invoice is deleted
- **THEN** its `invoice_number` is never assigned to a subsequently created buyer invoice

### Requirement: Buyer Credit Exposure Reconciliation
The system SHALL be able to detect disagreement between a buyer's stored `credit_used` counter and its credit exposure as derived from `buyer_orders`, via a read-only `erp:reconcile-credit-exposure` Artisan command, without mutating either value. The command SHALL treat differences within a configurable tolerance (default `0.01`) as agreement, print the buyer's name, id, team, stored value, derived value, and signed difference for every buyer outside tolerance, and exit non-zero if any buyer drifted.

`BuyerCreditUsageHistory` rows recording each credit debit, restore, and release SHALL continue to be written on every order confirmation, cancellation, and payment-driven release, independent of whether anything still reads them to compute current exposure — the ledger remains the audit trail of record even though it is no longer the basis for the live exposure figure.

#### Scenario: Stored and derived values agree
- **WHEN** `erp:reconcile-credit-exposure` runs and every buyer's stored `credit_used` is within tolerance of its derived `credit_exposure`
- **THEN** the command reports the count of buyers checked and exits `0`

#### Scenario: A buyer's stored counter has drifted from its derived exposure
- **WHEN** `erp:reconcile-credit-exposure` runs against a buyer whose stored `credit_used` disagrees with its derived `credit_exposure` by more than the tolerance
- **THEN** the command prints a `DRIFT` line naming the buyer, its id, its team, the stored value, the derived value, and the difference
- **AND** the command exits `1`
- **AND** no column is written by the command; the check is read-only

#### Scenario: Sub-cent differences are tolerated
- **WHEN** a buyer's stored `credit_used` differs from its derived `credit_exposure` by less than the `--tolerance` option (default `0.01`)
- **THEN** the buyer is not reported as drifted

#### Scenario: Audit ledger keeps recording even though it is no longer authoritative
- **WHEN** a buyer order is confirmed and reserves credit, has credit released by a payment, or has its credit restored on cancellation
- **THEN** a `BuyerCreditUsageHistory` row is created recording the transaction type, amount, and before/after snapshots
- **AND** this happens regardless of the fact that `Company::credit_exposure` is computed directly from `buyer_orders`, not from this ledger


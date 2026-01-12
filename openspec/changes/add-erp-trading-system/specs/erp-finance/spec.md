# ERP Finance

Invoicing, payments, profit/loss tracking, and tax calculation services.

## ADDED Requirements

### Requirement: Tax Calculation Service
The system SHALL provide a TaxCalculationService for consistent tax calculations across all quote/order items.

#### Scenario: Calculate tax-exclusive unit price
- **WHEN** user enters unit_price $5,772 with is_tax_inclusive = true and tax_rate 11%
- **THEN** TaxCalculationService calculates unit_price_exc_tax as $5,200 ($5,772 / 1.11)
- **AND** tax_amount per unit is $572

#### Scenario: Calculate from tax-exclusive price
- **WHEN** user enters unit_price $5,200 with is_tax_inclusive = false and tax_rate 11%
- **THEN** unit_price_exc_tax equals $5,200
- **AND** tax_amount per unit is $572
- **AND** total per unit is $5,772

#### Scenario: Calculate line totals
- **WHEN** item has quantity 2, unit_price_exc_tax $5,200, tax_rate 11%
- **THEN** subtotal is $10,400 (2 × $5,200)
- **AND** tax_amount is $1,144 (subtotal × 0.11)
- **AND** total is $11,544

#### Scenario: Handle zero tax rate
- **WHEN** tax_code has rate 0% (tax exempt or no tax)
- **THEN** tax_amount is $0
- **AND** subtotal equals total
- **AND** unit_price_exc_tax equals unit_price regardless of is_tax_inclusive

#### Scenario: Tax rate snapshot on save
- **WHEN** item is saved with tax_code_id
- **THEN** tax_rate is snapshotted from current tax_code.rate
- **AND** future changes to tax_code.rate don't affect this item

---

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

#### Scenario: Invoice numbering
- **WHEN** buyer invoice is created
- **THEN** invoice_number is auto-generated (e.g., "INV-2024-0089-1")
- **AND** sequence continues for same request (INV-2024-0089-2)

#### Scenario: Invoice status tracking
- **WHEN** invoice is created
- **THEN** status defaults to "draft"
- **AND** status can progress: draft → sent → partial → paid → overdue → cancelled
- **AND** issued_at is set when status becomes "sent"

#### Scenario: Calculate overdue status
- **WHEN** due_at has passed and status is not "paid"
- **THEN** status automatically changes to "overdue"
- **AND** days_overdue is calculated

---

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
The system SHALL calculate per-request P&L in base currency.

#### Scenario: Calculate buyer total
- **WHEN** buyer order exists
- **THEN** buyer_total is order.total
- **WHEN** no order exists
- **THEN** buyer_total is latest buyer_quote.total

#### Scenario: Calculate supplier cost
- **WHEN** request has supplier orders
- **THEN** supplier_cost is sum of all supplier_order.total_in_base

#### Scenario: Calculate gross margin
- **WHEN** buyer_total is $10,922 and supplier_cost is $8,623
- **THEN** gross_margin is $2,299
- **AND** margin_percent is 21.1%

#### Scenario: Track cash flow
- **WHEN** viewing P&L
- **THEN** collected shows sum of buyer payments received
- **AND** paid_out shows sum of supplier payments made
- **AND** net_cash_flow is collected - paid_out

#### Scenario: Display P&L on request
- **WHEN** viewing request Invoices tab
- **THEN** P&L summary is displayed
- **AND** shows revenue, costs, profit, margin, collections, payments

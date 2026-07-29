## MODIFIED Requirements

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

## ADDED Requirements

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

# ERP Quoting

Multi-supplier quoting workflow with consolidated buyer quotes.

## ADDED Requirements

### Requirement: Supplier Quotes
The system SHALL allow receiving quotes from multiple suppliers for a single request, each in their own currency.

#### Scenario: Create supplier quote
- **WHEN** an admin creates a supplier quote for MotorCorp with currency IDR
- **THEN** the quote is linked to the request and supplier
- **AND** status defaults to "pending"

#### Scenario: Add supplier quote items
- **WHEN** an admin adds item "Industrial Motor 5HP" qty 2 at Rp 37,500,000 each
- **THEN** the item total is calculated as Rp 75,000,000
- **AND** request_item_id links to the original buyer request item
- **AND** sort_order defaults to sequential position

#### Scenario: Apply exchange rate to supplier quote
- **WHEN** IDR/USD rate is 15,500 and supplier quote total is Rp 75,000,000
- **THEN** total_in_base is calculated as $4,838.71

#### Scenario: Multiple suppliers per request
- **WHEN** three suppliers provide quotes for the same request
- **THEN** all three quotes are associated with the request
- **AND** consolidated cost summary shows all three with conversions

#### Scenario: Select supplier quote
- **WHEN** an admin selects MotorCorp's quote for specific items
- **THEN** the quote status changes to "selected"
- **AND** other quotes for same items can be rejected

#### Scenario: Item-level tax on supplier quote (tax-exclusive price)
- **WHEN** admin adds item with unit_price Rp 37,500,000 and tax code "PPN 11%" (exclusive)
- **THEN** tax_rate is snapshotted as 11.00
- **AND** unit_price_exc_tax equals Rp 37,500,000
- **AND** tax_amount per unit equals Rp 4,125,000
- **AND** total per unit equals Rp 41,625,000

#### Scenario: Item-level tax on supplier quote (tax-inclusive price)
- **WHEN** admin adds item with unit_price Rp 41,625,000 and is_tax_inclusive = true, tax code "PPN 11%"
- **THEN** unit_price_exc_tax is calculated as Rp 37,500,000 (41,625,000 / 1.11)
- **AND** tax_amount per unit equals Rp 4,125,000
- **AND** total per unit equals Rp 41,625,000

#### Scenario: Mixed tax treatments on supplier quote
- **WHEN** supplier quote has items with different tax codes
- **THEN** each item calculates tax independently
- **AND** quote totals aggregate all item subtotals and tax amounts

#### Scenario: Reorder supplier quote items
- **WHEN** admin drags item 3 to position 1
- **THEN** sort_order is updated for affected items
- **AND** items display in new order

---

### Requirement: Buyer Quotes
The system SHALL allow creating consolidated buyer quotes from multiple supplier sources, with versioning support.

#### Scenario: Create buyer quote
- **WHEN** an admin creates a buyer quote from selected supplier quotes
- **THEN** quote_number is generated (e.g., "Q-2024-0089-v1")
- **AND** version defaults to 1
- **AND** items are consolidated from all selected supplier quotes

#### Scenario: Buyer quote items with margin
- **WHEN** cost_price is $4,838.71 and unit_price is set to $5,200
- **THEN** margin_percent is calculated as 7.5%
- **AND** total is quantity × unit_price_exc_tax + tax_amount
- **AND** sort_order defaults to sequential position

#### Scenario: Item-level tax on buyer quote (tax-exclusive price)
- **WHEN** admin adds item with unit_price $5,200 and tax code "PPN 11%" (exclusive)
- **THEN** tax_rate is snapshotted as 11.00
- **AND** unit_price_exc_tax equals $5,200
- **AND** tax_amount per unit equals $572
- **AND** total per unit equals $5,772

#### Scenario: Item-level tax on buyer quote (tax-inclusive price)
- **WHEN** admin adds item with unit_price $5,772 and is_tax_inclusive = true, tax code "PPN 11%"
- **THEN** unit_price_exc_tax is calculated as $5,200 ($5,772 / 1.11)
- **AND** margin_percent calculation uses unit_price_exc_tax vs cost_price
- **AND** total per unit equals $5,772

#### Scenario: Tax code from article default
- **WHEN** adding buyer quote item for article with default_tax_code_id
- **THEN** tax_code_id defaults to article's default
- **AND** is_tax_inclusive defaults to tax_code's is_inclusive_default

#### Scenario: Mixed tax treatments on buyer quote
- **WHEN** buyer quote has items with different tax codes (some exempt, some 11%)
- **THEN** each item calculates tax independently
- **AND** quote totals aggregate all item subtotals and tax amounts
- **AND** margin calculation uses tax-exclusive amounts

#### Scenario: Reorder buyer quote items
- **WHEN** admin drags item 3 to position 1
- **THEN** sort_order is updated for affected items
- **AND** items display in new order

#### Scenario: Payment terms on buyer quote
- **WHEN** admin sets prepayment_percent to 30% and net_days to 30
- **THEN** terms are stored on the quote
- **AND** displayed as "30% prepay, Net 30 from delivery"

#### Scenario: Set quote validity
- **WHEN** admin sets valid_until to 2024-01-22
- **THEN** original_valid_until is also set to 2024-01-22
- **AND** quote shows days until expiration

#### Scenario: Supplier info hidden from buyer
- **WHEN** generating PDF for buyer
- **THEN** supplier names and cost prices are NOT included
- **AND** only item description, quantity, unit, and sell price are shown

---

### Requirement: Buyer Quote Versioning
The system SHALL support creating revised versions of buyer quotes during negotiation.

#### Scenario: Create quote revision
- **WHEN** admin creates a revision of quote Q-2024-0089-v1
- **THEN** new quote Q-2024-0089-v2 is created with version 2
- **AND** parent_id links to v1
- **AND** v1 status changes to "superseded"

#### Scenario: Track quote version history
- **WHEN** viewing a buyer quote
- **THEN** all previous versions are accessible
- **AND** changes between versions can be compared

---

### Requirement: Quote Extensions
The system SHALL allow extending quote validity with mandatory reason logging instead of cancellation.

#### Scenario: Extend quote validity
- **WHEN** admin extends quote from 2024-01-22 to 2024-02-05
- **THEN** valid_until is updated to 2024-02-05
- **AND** original_valid_until remains 2024-01-22
- **AND** extension is logged in buyer_quote_extensions

#### Scenario: Extension requires reason
- **WHEN** extending a quote
- **THEN** reason field is required (text)
- **AND** extended_by user is recorded

#### Scenario: Extension tracks price changes
- **WHEN** extending a quote
- **THEN** prices_changed flag indicates if supplier costs changed
- **AND** availability_changed flag indicates if items still available
- **AND** change_notes captures details if applicable

#### Scenario: Extension appears in activity log
- **WHEN** a quote is extended
- **THEN** activity record is created with type "buyer_quote_extended"
- **AND** details include old date, new date, reason, and flags

---

### Requirement: Quote Status Flow
The system SHALL enforce a status-based workflow for quotes.

#### Scenario: Quote status transitions
- **WHEN** buyer quote progresses through: draft → sent → accepted
- **THEN** each status change is valid
- **AND** sent_at is recorded when status becomes "sent"
- **AND** accepted_at is recorded when status becomes "accepted"

#### Scenario: Quote acceptance creates order
- **WHEN** buyer quote status changes to "accepted"
- **THEN** system prompts to create buyer order from the quote
- **AND** request stage advances to "buyer_po_received"

#### Scenario: Quote rejection
- **WHEN** buyer rejects quote
- **THEN** status changes to "rejected"
- **AND** admin can create a new revision

#### Scenario: Quote expiration
- **WHEN** valid_until date passes without acceptance
- **THEN** quote can be extended or allowed to expire
- **AND** expired quotes are NOT automatically cancelled

---

### Requirement: Margin Analysis
The system SHALL calculate and display margin analysis on buyer quotes.

#### Scenario: Calculate gross margin
- **WHEN** buyer quote total (excl. tax) is $9,840 and supplier costs are $8,623
- **THEN** gross_margin is $1,217

#### Scenario: Calculate margin percentage
- **WHEN** gross_margin is $1,217 and selling price is $9,840
- **THEN** margin_percent is 12.4%

#### Scenario: Display margin indicator
- **WHEN** viewing buyer quote
- **THEN** margin is displayed with visual indicator
- **AND** color coding: green >15%, yellow 5-15%, red <5%

#### Scenario: Per-item margin
- **WHEN** viewing buyer quote items
- **THEN** each item shows cost_price, unit_price, and margin_percent

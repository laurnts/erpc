## MODIFIED Requirements

### Requirement: Margin Analysis
The system SHALL calculate and display margin analysis on buyer quotes using the
canonical on-selling (gross margin) formula defined in `MarginConvention`.

#### Scenario: Calculate gross margin
- **WHEN** buyer quote net sell total (excl. tax) is $9,840 and supplier costs are $8,623
- **THEN** gross_margin is $1,217

#### Scenario: Calculate margin percentage (on-selling)
- **WHEN** gross_margin is $1,217 and net sell total is $9,840
- **THEN** margin_percent is 12.4% (`1,217 / 9,840 × 100`)
- **AND** this formula (`(sell_net − cost) / sell_net × 100`) is the single definition used for all line-item margins, quote-level rollup margins, and P&L margin displays

#### Scenario: P&L margin uses net sell, not gross
- **WHEN** a buyer quote item (qty 2) has `is_tax_inclusive` true, `unit_price_exc_tax` $5,200, `cost_price` $4,600, tax 11%
- **THEN** `line_subtotal` is $10,400, `line_tax` is $1,144, `line_total` is $11,544
- **AND** the P&L margin for this item uses `line_subtotal` ($10,400) as the sell base
- **AND** NOT `line_total` ($11,544), which includes collected VAT
- **AND** `marginAmount` is $10,400 − $9,200 = $1,200
- **AND** `marginPercent` is 11.54%

#### Scenario: Display margin indicator
- **WHEN** viewing buyer quote
- **THEN** margin is displayed with visual indicator
- **AND** color coding: green ≥15%, yellow 5–14.99%, red <5%

#### Scenario: Per-item margin display is consistent
- **WHEN** viewing a buyer quote item in the form, the P&L infolist, and the P&L PDF
- **THEN** the same margin percentage is shown in all three views
- **AND** the per-item margin and the quote-level rollup margin use the same formula and base

---

### Requirement: Profit and Loss Document
The system SHALL allow generating Profit and Loss (PNL) documents for tracking
profitability of buyer quotes, with financial figures frozen at the moment of
approval.

#### Scenario: Central Purchasing fields
- **WHEN** admin creates or edits a Profit and Loss document
- **THEN** "Prepared By" field shows team members with Key Account role
- **AND** "Dept Head of Sales" field shows team members with Dept Head of Sales role
- **AND** "Deputy Director" field shows team members with Deputy Director role
- **AND** "Approved By" field shows team members with Director role
- **AND** all fields query team members instead of People records
- **AND** foreign key references store User IDs instead of People IDs

#### Scenario: PNL approval freezes financial figures
- **WHEN** all three approver timestamps are set on a PNL
- **THEN** a `FinancialSnapshot` is stored on the PNL record
- **AND** subsequent renders of the PNL view page and PDF use the snapshot totals
- **AND** the snapshot includes the net sell subtotal, tax total, grand total, cost total, margin amount, margin percent, currency, and the linked buyer quote ID at time of snapshot

#### Scenario: Approved PNL totals do not change after buyer quote soft-delete
- **WHEN** an approved PNL exists with a `FinancialSnapshot`
- **AND** the source buyer quote is later soft-deleted
- **THEN** the PNL view and PDF continue to show the snapshotted figures
- **AND** no automatic re-linking to a different buyer quote occurs

#### Scenario: Approved PNL totals do not change on page render
- **WHEN** an approved PNL page is loaded
- **THEN** no write operations occur as a side effect of rendering
- **AND** no queries are issued against `buyer_quote_items` to compute financial totals

---

### Requirement: Buyer Quotes
The system SHALL allow creating consolidated buyer quotes from multiple supplier
sources, with versioning support and consistent financial calculations across
all creation paths.

#### Scenario: Create buyer quote
- **WHEN** an admin creates a buyer quote from selected supplier quotes
- **THEN** `quote_number` is generated (e.g., "Q-2024-0089-v1")
- **AND** version defaults to 1
- **AND** items are consolidated from all selected supplier quotes

#### Scenario: Buyer quote items with margin
- **WHEN** `cost_price` is $4,838.71 and `unit_price_exc_tax` is $5,200
- **THEN** `margin_percent` is calculated as 6.95% (`(5200 − 4838.71) / 5200 × 100`)
- **AND** the same formula applies regardless of which creation path was used (single supplier, consolidated, or manual)
- **AND** `line_subtotal` is `quantity × unit_price_exc_tax`

#### Scenario: Prepayment synced across all creation paths
- **WHEN** a buyer quote with PERCENT prepayment is created via any path (single supplier, consolidated, or manual)
- **THEN** `prepayment_percent` is set to the entered percentage
- **AND** `prepayment_amount` is set to 0
- **AND** `ViewRequest::getPrepaymentDisplay()` shows the correct percentage

#### Scenario: Item-level tax on buyer quote (tax-exclusive price)
- **WHEN** admin adds item with unit_price $5,200 and tax code "PPN 11%" (exclusive)
- **THEN** `tax_rate` is snapshotted as 11.00
- **AND** `unit_price_exc_tax` equals $5,200
- **AND** `tax_amount` per unit equals $572
- **AND** total per unit equals $5,772

#### Scenario: Item-level tax on buyer quote (tax-inclusive price)
- **WHEN** admin adds item with unit_price $5,772 and `is_tax_inclusive` = true, tax code "PPN 11%"
- **THEN** `unit_price_exc_tax` is calculated as $5,200 ($5,772 / 1.11)
- **AND** `margin_percent` calculation uses `unit_price_exc_tax` vs `cost_price`
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

#### Scenario: Service child items excluded from buyer quote PDF total
- **WHEN** a buyer quote PDF is generated for a service request
- **THEN** the grand total on the PDF equals `BuyerQuote::total` (main items only)
- **AND** service child/detail breakdown lines are not independently summed into the PDF total
- **AND** the PDF total matches the figure used for margin and PNL generation

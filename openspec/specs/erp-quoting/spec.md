# erp-quoting Specification

## Purpose
TBD - created by archiving change add-erp-trading-system. Update Purpose after archive.
## Requirements
### Requirement: Generate Supplier Quotes from Item Assignments
The system SHALL allow generating supplier quotes automatically from request items that have been assigned to suppliers in the Items tab, pre-filling prices from the supplier's standing price where currency-compatible.

#### Scenario: Generate supplier quotes button
- **WHEN** an admin views the Items tab with items assigned to suppliers
- **THEN** a "Generate Supplier Quotes" button is available
- **AND** the button shows a confirmation with count of quotes to be created

#### Scenario: Generate quotes grouped by supplier
- **WHEN** an admin clicks "Generate Supplier Quotes"
- **THEN** one SupplierQuote is created per unique supplier_id from assigned items
- **AND** each quote contains SupplierQuoteItems for all items assigned to that supplier
- **AND** quote items are pre-populated with article name, quantity, and unit from request items

#### Scenario: Skip existing supplier quotes
- **WHEN** generating supplier quotes for a request
- **AND** a quote already exists for a supplier on this request
- **THEN** that supplier is skipped (no duplicate quotes)
- **AND** a notification informs how many quotes were created vs skipped

#### Scenario: No items assigned warning
- **WHEN** an admin clicks "Generate Supplier Quotes" with no items assigned to suppliers
- **THEN** a warning notification is shown
- **AND** no quotes are created

#### Scenario: Default currency on generated quotes
- **WHEN** supplier quotes are auto-generated
- **THEN** currency defaults to team's default ERP currency
- **AND** exchange_rate defaults to 1
- **AND** quoted_at defaults to current date

#### Scenario: Price prefill prefers the supplier standing price
- **WHEN** quote items are pre-populated for a supplier-article link
- **THEN** the unit price prefill uses the pivot `supplier_price` when set, falling back to `last_quoted_price`
- **AND** a price is only prefilled when its currency matches the quote currency — prices are never copied across currencies verbatim

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

### Requirement: Key Accounts Master Data
The system SHALL maintain Central Purchasing personnel for use in approval workflows through team member roles.

#### Scenario: Central Purchasing personnel selection
- **WHEN** admin selects Central Purchasing personnel for approval workflows
- **THEN** personnel are selected from team members with Central Purchasing role
- **AND** team members are filtered by their Central Purchasing sub-role (Key Account, Dept Head of Sales, Deputy Director, Director)
- **AND** personnel selection queries team members instead of People records

#### Scenario: Inline Central Purchasing personnel creation
- **WHEN** user clicks [+] button next to a Central Purchasing personnel select field
- **THEN** a form opens to create a new team member
- **AND** upon save, the new team member is created with Central Purchasing role and appropriate sub-role
- **AND** the new team member is auto-selected in the field

### Requirement: Quotation Evaluation Document
The system SHALL allow generating internal Quotation Evaluation (QE) documents from the Compare Supplier Quotes view for procurement documentation.

#### Scenario: Central Purchasing fields
- **WHEN** admin creates or edits a Quotation Evaluation
- **THEN** "Prepared By" field shows team members with Key Account role
- **AND** "Dept Head of Sales" field shows team members with Dept Head of Sales role
- **AND** "Deputy Director" field shows team members with Deputy Director role
- **AND** "Approved By" field shows team members with Director role
- **AND** all fields query team members instead of People records
- **AND** foreign key references store User IDs instead of People IDs

### Requirement: Quotation Evaluation Master Data Resource
The system SHALL provide a Master Data resource for viewing and managing Quotation Evaluations.

#### Scenario: QE list page
- **WHEN** navigating to Quotation Evaluations in Master Data
- **THEN** a table displays all QE documents
- **AND** columns include: QE Number, Request Number, Description, Date, Created By

#### Scenario: QE view page - QE Information section
- **WHEN** viewing a QE document
- **THEN** QE Information section displays QE Number, Date, Description
- **AND** Request Number is shown as a clickable link to the request

#### Scenario: QE view page - Item Comparison table
- **WHEN** viewing a QE document
- **THEN** Item Comparison table displays items from the snapshotted data
- **AND** columns show Item, Qty, and price per supplier
- **AND** each supplier cell displays unit price and item total (before tax)
- **AND** best price per item is marked with star icon
- **AND** Subtotal row shows sum of line totals before tax per supplier
- **AND** Tax row shows sum of tax amounts per supplier
- **AND** Grand Total row shows final total per supplier
- **AND** Item Comparison section is displayed full width

#### Scenario: QE view page - Supplier Information section
- **WHEN** viewing a QE document
- **THEN** Supplier Information section displays snapshotted supplier data
- **AND** shows Delivery Type, Taxable (Yes/No), Delivery Term, Payment Terms per supplier
- **AND** Supplier Information section is placed after Item Comparison section
- **AND** Supplier Information section is displayed full width

#### Scenario: QE view page - Central Purchasing section
- **WHEN** viewing a QE document
- **THEN** Central Purchasing section displays approval personnel
- **AND** shows Prepared By with name and email
- **AND** shows Acknowledged By with Dept Head of Sales and Deputy Director
- **AND** shows Approved By with name and email

#### Scenario: QE data snapshot persistence
- **WHEN** QE is saved
- **THEN** item comparison data is snapshotted (prices, quantities, totals)
- **AND** supplier information is snapshotted (delivery type, terms)
- **AND** changes to quotes or suppliers after save do not affect saved QE

#### Scenario: QE view page - Central Purchasing section layout
- **WHEN** viewing a QE document
- **THEN** Central Purchasing section is displayed full width with 4 columns
- **AND** shows Prepared By, Dept Head of Sales, Deputy Director, Approved By

#### Scenario: Download QE as PDF
- **WHEN** viewing a QE document
- **THEN** a "Download PDF" button is displayed in header actions
- **AND** clicking it downloads a PDF document in landscape A4 format
- **AND** PDF contains QE Information, Item Comparison table, Supplier Information, and Central Purchasing approval section with signature lines

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

### Requirement: PNL Status
The system SHALL compute and display PNL status based on buyer order existence.

#### Scenario: PNL status - Pending
- **WHEN** viewing a PNL document
- **AND** no buyer orders exist for the associated request
- **THEN** status is displayed as "Pending" with warning color

#### Scenario: PNL status - Ordered
- **WHEN** viewing a PNL document
- **AND** buyer orders exist for the associated request
- **THEN** status is displayed as "Ordered" with success color

#### Scenario: PNL status in list view
- **WHEN** viewing the PNL list page
- **THEN** Status column displays badge with Pending or Ordered

---

### Requirement: Profit and Loss Master Data Resource
The system SHALL provide a Master Data resource for viewing and managing Profit and Loss documents.

#### Scenario: PNL list page
- **WHEN** navigating to Profit & Loss in Master Data
- **THEN** a table displays all PNL documents
- **AND** columns include: PNL Number, Status, Request Number, Description, Date, Created By

#### Scenario: PNL view page - PNL Information section
- **WHEN** viewing a PNL document
- **THEN** PNL Information section displays PNL Number, Date, Status, Description
- **AND** Request Number is shown as a clickable link to the request

#### Scenario: PNL view page - Selected Items by Supplier section
- **WHEN** viewing a PNL document
- **THEN** Selected Items by Supplier section displays buyer quote items grouped by supplier
- **AND** each supplier group shows header with supplier name and cost/sell/margin totals
- **AND** item table shows columns: Item, Qty, Cost, Sell, Tax, Margin %, Line Total
- **AND** each supplier group shows subtotal row

#### Scenario: PNL view page - Central Purchasing section
- **WHEN** viewing a PNL document
- **THEN** Central Purchasing section displays approval personnel in 4 columns
- **AND** shows Prepared By (name only), Dept Head of Sales, Deputy Director, Approved By

#### Scenario: Download PNL as PDF
- **WHEN** viewing a PNL document
- **THEN** a "Download PDF" button is displayed in header actions
- **AND** clicking it downloads a PDF document in landscape A4 format
- **AND** PDF contains PNL Information, Items by Supplier with totals, Grand Total, and Central Purchasing approval section with signature lines

#### Scenario: PNL edit page
- **WHEN** editing a PNL document
- **THEN** user can modify Description field
- **AND** user can modify Central Purchasing fields
- **AND** PNL Number, Date, and Request are read-only

### Requirement: Request View Page Summary
The Request view page SHALL display a summary section with three columns: Financial Summary, Payment Terms, and Shipment information.

#### Scenario: Three-column layout display
- **WHEN** a user views a Request detail page
- **THEN** the summary area displays three columns side-by-side
- **AND** the first column shows Financial Summary (Buyer Total, Supplier Costs, Gross Margin, Margin %)
- **AND** the second column shows Payment Terms section
- **AND** the third column shows Shipment section

#### Scenario: Payment Terms section display
- **WHEN** a Request has an associated BuyerOrder with BuyerQuote
- **THEN** the Payment Terms section displays:
  - **AND** Prepayment value formatted as percentage (e.g., "10%") if prepayment_type is PERCENT
  - **AND** Prepayment value formatted as currency (e.g., "Rp 1,000,000") if prepayment_type is FIXED
  - **AND** List of payment terms showing due_days and percentage for each term
  - **AND** Payment status (Paid/Not Paid) for each payment term based on BuyerInvoice payment records

#### Scenario: Payment term status calculation
- **WHEN** a payment term has associated BuyerInvoice records with payments
- **AND** the total paid amount equals or exceeds the payment term amount
- **THEN** the status displays as "Paid"
- **WHEN** a payment term has no payments or partial payments
- **THEN** the status displays as "Not Paid"

#### Scenario: Shipment section display
- **WHEN** a Request has associated Shipment records
- **THEN** the Shipment section displays a list of shipments
- **AND** each shipment shows:
  - Shipment number (shipment_number)
  - Status badge (from ShipmentStatus enum)
  - Carrier name (carrier_name or "-" if not set)
  - Tracking number (tracking_number or "-" if not set)

#### Scenario: Empty state handling
- **WHEN** a Request has no BuyerOrder or BuyerQuote
- **THEN** Payment Terms section shows empty state or placeholder
- **WHEN** a Request has no Shipments
- **THEN** Shipment section shows empty state or placeholder

### Requirement: Quotation Evaluation Item Scope
The system SHALL make Quotation Evaluation available when a request has at least one goods item, and SHALL limit the evaluation's contents to goods items.

#### Scenario: QE on a mixed request covers goods items only
- **WHEN** an admin creates a Quotation Evaluation for a request with goods and services items
- **THEN** the evaluation lists supplier quote lines for goods items only
- **AND** services items are excluded from the comparison

#### Scenario: QE unavailable for service-only requests
- **WHEN** all of a request's items are services items
- **THEN** Quotation Evaluation creation is blocked with a notice that it applies to goods items only

---

### Requirement: Item-Type-Driven Quote Composition
The system SHALL derive quote structure per item from the item's type: services items carry their child-item breakdown into quotes, and job-progress payment terms are available when a quote's request has at least one services item.

#### Scenario: Supplier quote generation for a mixed request
- **WHEN** supplier quotes are generated for a request with a goods item and a services main item having two child items
- **THEN** the goods item produces one flat quote line
- **AND** the services main item produces a quote line with its two child lines nested beneath it
- **AND** child lines are excluded from quote totals

#### Scenario: Job progress on payment terms
- **WHEN** an admin edits payment terms on a quote whose request has at least one services item
- **THEN** the Job Progress (%) field is available on each payment-term row
- **AND** the field is absent when the request has only goods items

#### Scenario: Totals on mixed documents
- **WHEN** totals are computed for a quote covering goods lines and a services main line with children
- **THEN** goods lines and the services main line are summed
- **AND** child lines are always excluded from totals

### Requirement: RFQ Send Visibility Gate
The system SHALL record when a supplier quote solicitation was actually sent to the supplier, and SHALL expose RFQs to the supplier portal only after that send; auto-generated pending quotes without a send remain internal.

#### Scenario: Send stamps the gate
- **WHEN** staff use "Send to Suppliers" and the solicitation mail is dispatched for a supplier quote
- **THEN** `sent_to_supplier_at` is stamped on that quote
- **AND** the mail contains a supplier-portal deep link

#### Scenario: Unsent RFQs are invisible to suppliers
- **WHEN** pending supplier quotes are auto-generated by the request stage transition without a per-supplier send
- **THEN** those quotes do not appear in any supplier portal view or response until `sent_to_supplier_at` is set

#### Scenario: Backfill of prior sends
- **WHEN** the column is introduced
- **THEN** `sent_to_supplier_at` is backfilled for quotes whose notification metadata records a previous successful send

### Requirement: Supplier Quote Portal Submission
The system SHALL allow the supplier to submit their quotation for a sent RFQ from the supplier portal, performing the same write as the internal price-entry flow, with the exchange rate always resolved server-side.

#### Scenario: Supplier submits a quote
- **WHEN** a supplier portal user submits per-item unit prices, validity date, notes, and an optional quotation document for their own sent, pending, undeclined, unexpired quote
- **THEN** the quote items are updated exactly as the internal "Input price" flow would update them
- **AND** `submitted_via = portal`, `submitted_at`, and `submitted_by_user_id` are stamped
- **AND** the existing pending→received status transition and downstream comparison/evaluation machinery operate unchanged
- **AND** the internal team is notified

#### Scenario: Exchange rate cannot be supplied by the client
- **WHEN** a portal submission arrives with any client-provided exchange rate value
- **THEN** the value is rejected/stripped and the rate is resolved server-side from the exchange-rate table
- **AND** this rule holds for tampered payloads, since the rate drives base-currency comparison ranking

### Requirement: Supplier Quote Decline
The system SHALL let a supplier decline a sent RFQ; declined quotes keep their pending status but are excluded from reminders and expiry, and a staff re-send resets the decline.

#### Scenario: Supplier declines
- **WHEN** a supplier portal user declines their sent, pending quote
- **THEN** `declined_at` is stamped, the internal team is notified, and the quote renders as "Declined" in both portal and admin views (taking precedence over "Expired")

#### Scenario: Declined quotes stop nagging and never expire
- **WHEN** the awaiting-supplier-quotes reminder job or the expiry sweep runs
- **THEN** quotes with `declined_at` set are skipped — a declined quote is neither nagged about nor mutated to expired

#### Scenario: Re-send resets a decline
- **WHEN** staff re-send the RFQ to a supplier whose quote row has `declined_at` set
- **THEN** `declined_at` and the `submitted_*` fields are cleared and `sent_to_supplier_at` is re-stamped
- **AND** the portal shows it as a fresh open RFQ

### Requirement: RFQ Outcome Announcement
The system SHALL finalize supplier quote outcomes only through an explicit staff announcement action at a terminal event; selection during evaluation SHALL remain reversible and invisible to suppliers.

#### Scenario: Selection churn stays internal
- **WHEN** staff apply or re-apply selections during comparison/evaluation
- **THEN** winners become selected and losers remain received exactly as today, freely re-applicable
- **AND** no supplier-facing notification fires and the supplier portal continues to show submitted quotes as "Submitted — under review"

#### Scenario: Announce outcomes
- **WHEN** staff run "Announce outcomes" (offered at quotation evaluation approval and prompted at supplier order issuance)
- **THEN** sibling received quotes with zero selected items are marked rejected
- **AND** a single won/lost notification is sent to each affected supplier portal user
- **AND** further selection re-application for the round is locked

#### Scenario: Announced losers remain visible internally
- **WHEN** outcomes have been announced
- **THEN** the comparison matrix and the quotation evaluation snapshot continue to include rejected quotes for display (read-only)
- **AND** outcome-only status transitions do not re-sync evaluation snapshots or reset an approved evaluation

#### Scenario: Staff data-entry shortcut fires no announcement
- **WHEN** a quote jumps to selected via the staff "obtained" data-entry shortcut without an announcement
- **THEN** no supplier-facing outcome notification is sent


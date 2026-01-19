# erp-quoting Specification

## Purpose
TBD - created by archiving change add-erp-trading-system. Update Purpose after archive.
## Requirements
### Requirement: Generate Supplier Quotes from Item Assignments
The system SHALL allow generating supplier quotes automatically from request items that have been assigned to suppliers in the Items tab.

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

---

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

### Requirement: Key Accounts Master Data
The system SHALL maintain a master data table of Key Accounts for use in approval workflows.

#### Scenario: Create key account
- **WHEN** admin creates a new Key Account
- **THEN** Name, Email, and Phone Number are stored
- **AND** the key account is associated with the current team

#### Scenario: Key account resource in Master Data
- **WHEN** navigating to Master Data section
- **THEN** Key Accounts is listed as a resource
- **AND** admin can view, create, edit, and delete key accounts

#### Scenario: Inline key account creation
- **WHEN** user clicks [+] button next to a key account select field
- **THEN** a modal form opens with Name, Email, Phone fields
- **AND** upon save, the new key account is created and auto-selected

---

### Requirement: Quotation Evaluation Document
The system SHALL allow generating internal Quotation Evaluation (QE) documents from the Compare Supplier Quotes view for procurement documentation.

#### Scenario: Create QE button visibility
- **WHEN** viewing Compare Supplier Quotes with at least one quote
- **THEN** a "Create QE" button is displayed next to "Select Best Prices"
- **AND** clicking it opens a slide-over modal from the right

#### Scenario: QE creation form fields
- **WHEN** the QE creation form modal opens
- **THEN** QE Number shows placeholder "Auto-generated after save"
- **AND** Date shows current date (read-only)
- **AND** Request number is displayed (read-only, from current request)
- **AND** Description is pre-filled with project name (editable)
- **AND** Central Purchasing section shows approval personnel fields

#### Scenario: QE number format generation
- **WHEN** saving a new QE document
- **THEN** QE number is generated with format `{increment}-DS/QE/{roman_month}/{year}`
- **AND** increment is sequential per team per year (resets each year)
- **AND** month is displayed as roman numeral (I-XII)

#### Scenario: Central Purchasing fields
- **WHEN** viewing the QE creation form
- **THEN** "Prepared By" field shows Key Account select with [+] button
- **AND** "Acknowledged By" has two sub-fields: "Dept Head of Sales" and "Deputy Director"
- **AND** "Approved By" field shows Key Account select with [+] button

#### Scenario: Redirect after QE creation
- **WHEN** user clicks "Save QE" and save succeeds
- **THEN** user is redirected to the QE View page in Master Data
- **AND** success notification is displayed

---

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
The system SHALL allow generating internal Profit and Loss (PNL) documents from the Buyer Quotes view for financial tracking and approval workflows.

#### Scenario: Create PNL button visibility
- **WHEN** viewing Buyer Quotes section for a request
- **AND** at least one buyer quote exists
- **THEN** a "Create PNL" button is displayed in the header actions
- **AND** clicking it opens a modal form

#### Scenario: PNL creation form fields
- **WHEN** the PNL creation modal opens
- **THEN** PNL Number shows placeholder "Auto-generated after save"
- **AND** Date shows current date (editable)
- **AND** Request number is displayed (read-only, from current request)
- **AND** Description field is available (editable)
- **AND** Central Purchasing section shows approval personnel fields

#### Scenario: PNL number format generation
- **WHEN** saving a new PNL document
- **THEN** PNL number is generated with format `{4-digit increment}/EL-PNL/{roman_month}/{year}`
- **AND** increment is sequential per team per year (resets each year)
- **AND** month is displayed as roman numeral (I-XII)

#### Scenario: Central Purchasing fields
- **WHEN** viewing the PNL creation form
- **THEN** "Prepared By" field shows Key Account select with create option (name only, no email)
- **AND** "Dept Head of Sales" field is a text input
- **AND** "Deputy Director" field is a text input
- **AND** "Approved By" field is a text input

#### Scenario: PNL links to buyer quote
- **WHEN** saving a new PNL document
- **THEN** PNL is linked to the latest valid buyer quote (excluding rejected/superseded status)
- **AND** buyer_quote_id is stored for reference

#### Scenario: Redirect after PNL creation
- **WHEN** user clicks "Create PNL" and save succeeds
- **THEN** user is redirected to the PNL View page in Master Data
- **AND** success notification is displayed

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


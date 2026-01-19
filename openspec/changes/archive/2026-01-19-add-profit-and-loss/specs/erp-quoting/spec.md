## ADDED Requirements

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

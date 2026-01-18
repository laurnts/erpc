## ADDED Requirements

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

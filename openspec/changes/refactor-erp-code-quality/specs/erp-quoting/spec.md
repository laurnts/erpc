## ADDED Requirements

### Requirement: Prepayment Type Enum
The system SHALL use a typed enum for prepayment type values to ensure type safety and consistency.

#### Scenario: Prepayment type options
- **WHEN** admin selects prepayment type on buyer quote
- **THEN** options are "Percentage (%)" and "Fixed Amount"
- **AND** values are backed by `PrepaymentType` enum

#### Scenario: Prepayment type validation
- **WHEN** admin enters prepayment amount
- **AND** type is PERCENT
- **THEN** amount is validated to be between 0 and 100
- **AND** suffix displays "%"

#### Scenario: Prepayment type fixed amount
- **WHEN** admin enters prepayment amount
- **AND** type is FIXED
- **THEN** amount has no upper limit
- **AND** suffix displays currency symbol

---

### Requirement: Unit of Measure Enum
The system SHALL use a typed enum for unit of measure values across all item models.

#### Scenario: Default unit selection
- **WHEN** admin creates a new item
- **THEN** unit defaults to "pcs" (pieces)
- **AND** unit is backed by `Unit` enum

#### Scenario: Unit options in forms
- **WHEN** admin selects unit on any item form
- **THEN** options include: pcs, kg, mt, set, box, roll, pair, L, m
- **AND** labels are human-readable

#### Scenario: Unit consistency across models
- **WHEN** items are created in different contexts (request, supplier quote, buyer quote, order)
- **THEN** all use the same `Unit` enum
- **AND** values are consistent across the system

---

### Requirement: Key Account Creation Action
The system SHALL provide a centralized action for creating key accounts to eliminate code duplication.

#### Scenario: Create key account via action
- **WHEN** creating a key account from any location (QE form, PNL form, relation manager)
- **THEN** the `CreateKeyAccount` action is invoked
- **AND** `team_id` and `creator_id` are auto-assigned via observer

#### Scenario: Key account inline creation
- **WHEN** user clicks [+] button next to key account select
- **THEN** modal opens with name, email, phone fields
- **AND** save invokes `CreateKeyAccount` action
- **AND** new key account is auto-selected

---

### Requirement: Document Number Generation Utilities
The system SHALL use shared utilities for generating document numbers with roman numeral months.

#### Scenario: QE number generation
- **WHEN** saving a new Quotation Evaluation
- **THEN** `RomanNumerals::month()` is used for month formatting
- **AND** format remains `{increment}-DS/QE/{roman_month}/{year}`

#### Scenario: PNL number generation
- **WHEN** saving a new Profit and Loss document
- **THEN** `RomanNumerals::month()` is used for month formatting
- **AND** format remains `{4-digit increment}/EL-PNL/{roman_month}/{year}`

---

## MODIFIED Requirements

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
- **AND** uniqueness is enforced per team (not globally)

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

#### Scenario: PNL team_id auto-assignment
- **WHEN** creating a new PNL document
- **THEN** `team_id` is automatically assigned from current tenant via observer
- **AND** `creator_id` is automatically assigned from authenticated user via observer

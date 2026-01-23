## ADDED Requirements

### Requirement: Delivery Type Enum
The system SHALL use a typed enum for supplier delivery type classifications.

#### Scenario: Delivery type options
- **WHEN** admin selects delivery type on supplier/company
- **THEN** options include standard Incoterms: FOB, CIF, EXW, DDP, DAP
- **AND** values are backed by `DeliveryType` enum

#### Scenario: Delivery type labels
- **WHEN** displaying delivery type
- **THEN** labels show full description (e.g., "FOB - Free on Board")
- **AND** abbreviation is used in compact views

#### Scenario: Delivery type on supplier info
- **WHEN** viewing supplier information in QE or comparison views
- **THEN** delivery type is displayed using enum label
- **AND** enum provides consistent formatting

---

### Requirement: ERP Default Constants
The system SHALL use centralized constants for default values to ensure consistency and maintainability.

#### Scenario: Quote validity default
- **WHEN** creating a new buyer quote
- **AND** team settings do not specify validity days
- **THEN** `ErpDefaults::QUOTE_VALIDITY_DAYS` (30) is used

#### Scenario: Payment terms default
- **WHEN** creating a new buyer quote
- **AND** team settings do not specify payment terms
- **THEN** `ErpDefaults::PAYMENT_TERMS_DAYS` (30) is used

#### Scenario: Currency default
- **WHEN** creating a new quote or order
- **AND** team settings do not specify currency
- **THEN** `ErpDefaults::CURRENCY_CODE` ('USD') is used

#### Scenario: Unit default
- **WHEN** creating a new item
- **THEN** `ErpDefaults::DEFAULT_UNIT` ('pcs') is used as default

---

### Requirement: Roman Numeral Utility
The system SHALL provide a utility class for converting numbers to roman numerals for document numbering.

#### Scenario: Month to roman numeral
- **WHEN** generating document numbers with month component
- **THEN** `RomanNumerals::month(1)` returns 'I'
- **AND** `RomanNumerals::month(12)` returns 'XII'

#### Scenario: Invalid month handling
- **WHEN** `RomanNumerals::month()` receives invalid month (0, 13, negative)
- **THEN** an `InvalidArgumentException` is thrown

---

### Requirement: Observer Auto-Assignment Pattern
The system SHALL use observers to automatically assign team_id and creator_id on ERP entity creation.

#### Scenario: Team ID auto-assignment
- **WHEN** creating a new ERP entity (KeyAccount, QuotationEvaluation, ProfitAndLoss)
- **AND** `team_id` is not explicitly set
- **THEN** observer assigns `team_id` from current Filament tenant

#### Scenario: Creator ID auto-assignment
- **WHEN** creating a new ERP entity
- **AND** `creator_id` is not explicitly set
- **THEN** observer assigns `creator_id` from authenticated user

#### Scenario: Observer does not override explicit values
- **WHEN** creating an entity with explicit `team_id` or `creator_id`
- **THEN** observer preserves the explicit values
- **AND** does not override with auto-detected values

---

### Requirement: Key Account Scopes
The system SHALL provide query scopes for common key account filtering patterns.

#### Scenario: Active for current team scope
- **WHEN** querying key accounts for select options
- **THEN** `KeyAccount::activeForCurrentTeam()` returns only active key accounts for current tenant

#### Scenario: Select options method
- **WHEN** building key account select field
- **THEN** `KeyAccount::selectOptions()` returns array formatted for Filament select
- **AND** format is `[id => display_name]`

---

### Requirement: Test Coverage for ERP Features
The system SHALL maintain minimum 80% test coverage for all ERP features.

#### Scenario: Key Account feature tests
- **WHEN** running test suite
- **THEN** tests cover CRUD operations for Key Accounts
- **AND** tests verify team scoping
- **AND** tests verify policy authorization

#### Scenario: Quotation Evaluation feature tests
- **WHEN** running test suite
- **THEN** tests cover QE creation from request
- **AND** tests verify QE number generation format
- **AND** tests verify data snapshot persistence

#### Scenario: Profit and Loss feature tests
- **WHEN** running test suite
- **THEN** tests cover PNL creation
- **AND** tests verify PNL number generation format
- **AND** tests verify status computation logic

#### Scenario: Enum unit tests
- **WHEN** running test suite
- **THEN** tests cover all enum cases and methods
- **AND** tests verify label, color, and helper method outputs

---

### Requirement: Reusable Form Components
The system SHALL provide reusable form components to eliminate code duplication across Filament resources.

#### Scenario: Central Purchasing schema component
- **WHEN** building a form that requires Central Purchasing fields
- **THEN** `CentralPurchasingSchema::make()` returns prepared_by, dept_head, deputy_director, approved_by fields
- **AND** Key Account select supports inline creation via `CreateKeyAccount` action
- **AND** component is used by QE, PNL, and BuyerQuotes forms

#### Scenario: TaxCode select component
- **WHEN** building a form that requires tax code selection
- **THEN** `TaxCodeSelect::make()` returns a configured select field
- **AND** options are filtered by current team
- **AND** default is set from team ERP settings
- **AND** article default tax code is supported via callback

---

### Requirement: Item Calculation Trait
The system SHALL provide a shared trait for calculating line item totals in relation managers.

#### Scenario: Calculate item totals with tax-exclusive price
- **WHEN** user enters quantity and unit price
- **AND** tax code has rate of 11%
- **THEN** line_subtotal equals quantity × unit_price
- **AND** line_tax equals subtotal × tax_rate / 100
- **AND** line_total equals subtotal + tax

#### Scenario: Calculate item totals with tax-inclusive price
- **WHEN** user enters quantity and unit price
- **AND** is_tax_inclusive is true
- **AND** tax rate is 11%
- **THEN** line_total equals quantity × unit_price
- **AND** line_subtotal equals total / (1 + rate)
- **AND** line_tax equals total - subtotal

#### Scenario: Calculate margin on buyer quote items
- **WHEN** calculating buyer quote item totals
- **THEN** margin_amount equals unit_price_exc_tax - cost_price
- **AND** margin_percent equals (margin_amount / cost_price) × 100

---

### Requirement: Currency Formatting Trait
The system SHALL provide a shared trait for consistent currency formatting.

#### Scenario: Format currency with team base currency
- **WHEN** formatting a currency value
- **AND** team has a base currency configured
- **THEN** value is formatted using currency's format method
- **AND** includes currency symbol and decimal places

#### Scenario: Format currency fallback
- **WHEN** formatting a currency value
- **AND** team has no base currency
- **THEN** value is formatted using number_format with 2 decimal places

---

### Requirement: PDF Generation Service Consolidation
The system SHALL centralize all PDF generation in the PdfGenerationService.

#### Scenario: Generate QE PDF via service
- **WHEN** downloading QE PDF
- **THEN** `PdfGenerationService::generateQuotationEvaluationPdf()` is called
- **AND** PDF is rendered in landscape A4 format
- **AND** filename follows pattern `QE-{number}.pdf`

#### Scenario: Generate PNL PDF via service
- **WHEN** downloading PNL PDF
- **THEN** `PdfGenerationService::generateProfitAndLossPdf()` is called
- **AND** PDF is rendered in landscape A4 format
- **AND** filename follows pattern `PNL-{number}.pdf`

#### Scenario: DownloadPdfAction supports all document types
- **WHEN** using DownloadPdfAction on any supported model
- **THEN** action detects model type and calls appropriate service method
- **AND** supports: BuyerQuote, BuyerOrder, BuyerInvoice, SupplierOrder, QuotationEvaluation, ProfitAndLoss

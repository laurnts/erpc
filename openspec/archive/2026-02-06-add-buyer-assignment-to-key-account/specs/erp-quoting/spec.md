## MODIFIED Requirements

### Requirement: Quotation Evaluation Document
The system SHALL allow generating internal Quotation Evaluation (QE) documents from the Compare Supplier Quotes view for procurement documentation.

#### Scenario: Central Purchasing fields
- **WHEN** admin creates or edits a Quotation Evaluation
- **THEN** "Prepared By" field shows team members with Key Account role
- **AND** "Prepared By" field is filtered to only show key accounts assigned to handle the request's buyer
- **AND** if no key accounts are assigned to the buyer, all key accounts are shown (fallback behavior)
- **AND** "Dept Head of Sales" field shows team members with Dept Head of Sales role
- **AND** "Deputy Director" field shows team members with Deputy Director role
- **AND** "Approved By" field shows team members with Director role
- **AND** all fields query team members instead of People records
- **AND** foreign key references store User IDs instead of People IDs

### Requirement: Profit and Loss Document
The system SHALL allow generating Profit and Loss (PNL) documents for tracking profitability of buyer quotes.

#### Scenario: Central Purchasing fields
- **WHEN** admin creates or edits a Profit and Loss document
- **THEN** "Prepared By" field shows team members with Key Account role
- **AND** "Prepared By" field is filtered to only show key accounts assigned to handle the buyer quote's buyer
- **AND** if no key accounts are assigned to the buyer, all key accounts are shown (fallback behavior)
- **AND** "Dept Head of Sales" field shows team members with Dept Head of Sales role
- **AND** "Deputy Director" field shows team members with Deputy Director role
- **AND** "Approved By" field shows team members with Director role
- **AND** all fields query team members instead of People records
- **AND** foreign key references store User IDs instead of People IDs

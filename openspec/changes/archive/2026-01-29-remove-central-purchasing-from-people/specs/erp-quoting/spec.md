## MODIFIED Requirements

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

### Requirement: Profit and Loss Document
The system SHALL allow generating Profit and Loss (PNL) documents for tracking profitability of buyer quotes.

#### Scenario: Central Purchasing fields
- **WHEN** admin creates or edits a Profit and Loss document
- **THEN** "Prepared By" field shows team members with Key Account role
- **AND** "Dept Head of Sales" field shows team members with Dept Head of Sales role
- **AND** "Deputy Director" field shows team members with Deputy Director role
- **AND** "Approved By" field shows team members with Director role
- **AND** all fields query team members instead of People records
- **AND** foreign key references store User IDs instead of People IDs

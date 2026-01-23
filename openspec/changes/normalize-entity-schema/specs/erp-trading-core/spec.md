## MODIFIED Requirements

### Requirement: Key Accounts Master Data
The system SHALL maintain key accounts as a filtered view of People for use in approval workflows.

#### Scenario: Create key account
- **WHEN** admin creates a new Key Account
- **THEN** a People record is created with `is_key_account=true`
- **AND** Name, Email, and Phone Number are stored on People record
- **AND** the key account is associated with the current team

#### Scenario: Key account resource in Master Data
- **WHEN** navigating to Master Data section
- **THEN** Key Accounts is listed as a resource
- **AND** list shows People records where `is_key_account=true`
- **AND** admin can view, create, edit, and deactivate key accounts

#### Scenario: Inline key account creation
- **WHEN** user clicks [+] button next to a key account select field
- **THEN** a modal form opens with Name, Email, Phone fields
- **AND** upon save, a People record is created with `is_key_account=true`
- **AND** the new person is auto-selected

#### Scenario: Key account select options
- **WHEN** displaying key account select field
- **THEN** options show active People where `is_key_account=true`
- **AND** options are filtered by current team
- **AND** inactive key accounts are excluded

---

### Requirement: Quotation Evaluation Document
The system SHALL store approval personnel as relationships to People.

#### Scenario: QE creation form fields
- **WHEN** the QE creation form modal opens
- **THEN** "Prepared By" field shows People select (key accounts)
- **AND** "Dept Head of Sales" field shows People select (key accounts)
- **AND** "Deputy Director" field shows People select (key accounts)
- **AND** "Approved By" field shows People select (key accounts)
- **AND** all fields support inline person creation

#### Scenario: QE approval personnel storage
- **WHEN** saving a QE document
- **THEN** `prepared_by_id` stores FK to People
- **AND** `dept_head_sales_id` stores FK to People
- **AND** `deputy_director_id` stores FK to People
- **AND** `approved_by_id` stores FK to People

#### Scenario: QE view page - Central Purchasing section
- **WHEN** viewing a QE document
- **THEN** Central Purchasing section displays approval personnel names from People relationships
- **AND** names are clickable links to People view page

---

### Requirement: Profit and Loss Document
The system SHALL store approval personnel as relationships to People.

#### Scenario: PNL creation form fields
- **WHEN** the PNL creation form modal opens
- **THEN** "Prepared By" field shows People select (key accounts)
- **AND** "Dept Head of Sales" field shows People select (key accounts)
- **AND** "Deputy Director" field shows People select (key accounts)
- **AND** "Approved By" field shows People select (key accounts)
- **AND** all fields support inline person creation

#### Scenario: PNL approval personnel storage
- **WHEN** saving a PNL document
- **THEN** `prepared_by_id` stores FK to People
- **AND** `dept_head_sales_id` stores FK to People
- **AND** `deputy_director_id` stores FK to People
- **AND** `approved_by_id` stores FK to People

#### Scenario: PNL view page - Central Purchasing section
- **WHEN** viewing a PNL document
- **THEN** Central Purchasing section displays approval personnel names from People relationships
- **AND** names are clickable links to People view page

---

## ADDED Requirements

### Requirement: Key Account Select Component
The system SHALL provide a reusable form component for selecting key account personnel.

#### Scenario: Key account select filtering
- **WHEN** using KeyAccountSelect component
- **THEN** options are filtered to People with `is_key_account=true`
- **AND** options are filtered by current team
- **AND** only active people are shown

#### Scenario: Key account inline creation
- **WHEN** user creates person via KeyAccountSelect
- **THEN** new People record has `is_key_account=true`
- **AND** new record is immediately selectable

#### Scenario: Key account select with relationship
- **WHEN** KeyAccountSelect is bound to a relationship field
- **THEN** selected person ID is stored as foreign key
- **AND** relationship is properly loaded on edit

---

### Requirement: Approval Personnel Reporting
The system SHALL support querying documents by approval personnel.

#### Scenario: Filter documents by approver
- **WHEN** filtering QE or PNL list
- **THEN** filter options include all approval personnel fields
- **AND** selecting a person shows documents where they are assigned

#### Scenario: Person approval history
- **WHEN** viewing a People record (key account)
- **THEN** related QE and PNL documents are accessible
- **AND** shows documents where person is prepared_by, approved_by, etc.

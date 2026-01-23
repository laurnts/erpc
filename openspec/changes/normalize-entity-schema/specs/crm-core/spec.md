## MODIFIED Requirements

### Requirement: People Management
The system SHALL allow teams to manage people (contacts) with extended profile information.

#### Scenario: Create person with contact details
- **WHEN** admin creates a new person
- **THEN** name is required
- **AND** email is optional and validated as email format
- **AND** phone is optional
- **AND** job_title is optional
- **AND** person is associated with current team

#### Scenario: Person email uniqueness
- **WHEN** admin creates a person with an email
- **AND** another person in the same team has that email
- **THEN** validation error is shown
- **AND** person is not created

#### Scenario: Person email uniqueness across teams
- **WHEN** admin creates a person with an email
- **AND** a person in a different team has that email
- **THEN** person is created successfully
- **AND** email uniqueness is per-team

#### Scenario: Mark person as key account
- **WHEN** admin creates or edits a person
- **THEN** "Key Account" toggle is available
- **AND** key accounts appear in approval workflow selects

#### Scenario: Deactivate person
- **WHEN** admin deactivates a person
- **THEN** person is marked as inactive
- **AND** inactive persons are excluded from select dropdowns
- **AND** existing references to the person are preserved

---

## ADDED Requirements

### Requirement: Contact Role Types
The system SHALL provide typed roles for company-person relationships.

#### Scenario: Assign contact role
- **WHEN** admin assigns a person to a company
- **THEN** role select shows: Primary, Billing, Technical, Sales, Support, Other
- **AND** selected role is stored as enum value

#### Scenario: Primary contact designation
- **WHEN** admin assigns a person with role "Primary"
- **THEN** person is marked as primary contact for the company
- **AND** only one primary contact per company is allowed

#### Scenario: Filter contacts by role
- **WHEN** viewing company contacts
- **THEN** contacts can be filtered by role
- **AND** role badges are displayed on contact list

---

### Requirement: Company Contact Person Relationship
The system SHALL link company contact person to People entity.

#### Scenario: Set contact person
- **WHEN** admin sets contact person for a company
- **THEN** select shows people associated with the company
- **AND** select allows creating new person inline
- **AND** selected person is linked via foreign key

#### Scenario: Contact person in company list
- **WHEN** viewing company list
- **THEN** contact person name is displayed from linked People record
- **AND** clicking name navigates to person view

#### Scenario: Contact person removal
- **WHEN** linked contact person is deleted
- **THEN** company contact_person_id is set to null
- **AND** company record is preserved

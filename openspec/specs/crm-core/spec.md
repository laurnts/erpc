# CRM Core Capability

## Purpose

Core CRM functionality for managing Companies, People, Opportunities, Tasks, and Notes with team-based multi-tenancy.
## Requirements
### Requirement: Company Management
The system SHALL provide CRUD operations for Company entities with team-based isolation.

#### Scenario: Create Company
- **WHEN** a user creates a company with valid data
- **THEN** the company is saved with the current team and creator assigned

#### Scenario: View Company
- **WHEN** a user views a company
- **THEN** they see company details including custom fields, associated people (contacts), notes, and tasks
- **AND** the contacts count is displayed in the list view

#### Scenario: Assign People to Company
- **WHEN** a user assigns people to a company
- **THEN** the associations are stored in the company_people pivot table
- **AND** each association can have a role and primary contact flag

#### Scenario: Inline Create Person from Company
- **WHEN** user creates a Company and clicks (+) on People field
- **THEN** inline Person form shows: Name, CustomFields (Emails, Phone, Job Title, LinkedIn)
- **AND** Companies field is excluded (circular reference)
- **AND** created Person is linked to the new Company

#### Scenario: List Companies
- **WHEN** a user lists companies
- **THEN** only companies belonging to their current team are shown

#### Scenario: Delete Company
- **WHEN** a user deletes a company
- **THEN** the company is soft-deleted and can be restored

### Requirement: People Management
The system SHALL allow users to create and manage People records.

#### Scenario: Create Person
- **WHEN** a user creates a person
- **THEN** the person is created with name and optional company assignments
- **AND** Central Purchasing role fields are no longer available

#### Scenario: Edit Person
- **WHEN** a user edits a person
- **THEN** they can update name and company assignments
- **AND** Central Purchasing toggle and role selection are not shown

#### Scenario: View Person
- **WHEN** a user views a person
- **THEN** they see person details and related information
- **AND** BuyersRelationManager is shown based on team member role (not People Central Purchasing role)

### Requirement: Opportunity Management
The system SHALL provide CRUD operations for Opportunities (deals) with pipeline tracking.

#### Scenario: Create Opportunity
- **WHEN** a user creates an opportunity with valid data
- **THEN** the opportunity is saved with associated company and people

#### Scenario: View Opportunity
- **WHEN** a user views an opportunity
- **THEN** they see opportunity details including amount, stage, and related entities

#### Scenario: Track Opportunity Progress
- **WHEN** a user updates opportunity stage
- **THEN** the flowforge position is updated for Kanban ordering

### Requirement: Task Management
The system SHALL provide CRUD operations for Tasks with polymorphic relationships.

#### Scenario: Create Task
- **WHEN** a user creates a task
- **THEN** the task is saved with optional assignments to companies, people, or opportunities

#### Scenario: Assign Task
- **WHEN** a user assigns people to a task
- **THEN** the many-to-many relationship is created via taskables table

#### Scenario: Complete Task
- **WHEN** a user marks a task as complete
- **THEN** the task status is updated

### Requirement: Note Management
The system SHALL provide CRUD operations for Notes with polymorphic relationships.

#### Scenario: Create Note
- **WHEN** a user creates a note on an entity (company, person, opportunity)
- **THEN** the note is saved with the polymorphic noteable relationship

#### Scenario: View Notes
- **WHEN** a user views an entity
- **THEN** they see all associated notes in reverse chronological order


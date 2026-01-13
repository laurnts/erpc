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
The system SHALL provide CRUD operations for People (contacts) entities with many-to-many company relationships.

#### Scenario: Create Person
- **WHEN** a user creates a person with valid data
- **THEN** the person is saved with optional company relationships via pivot table

#### Scenario: Assign Person to Multiple Companies
- **WHEN** a user assigns a person to multiple companies
- **THEN** the associations are stored in the company_people pivot table
- **AND** each association can have a role and primary flag

#### Scenario: Set Primary Company
- **WHEN** a user sets a company as the primary company for a person
- **THEN** the is_primary flag is set to true on the pivot record
- **AND** previous primary designation is cleared

#### Scenario: Inline Create Company from Person
- **WHEN** user creates a Person and clicks (+) on Companies field
- **THEN** inline Company form matches Company → Create Company form
- **AND** People field is excluded (circular reference)
- **AND** Code field is excluded (auto-generated in main form only)
- **AND** created Company is linked to the new Person

#### Scenario: View Person
- **WHEN** a user views a person
- **THEN** they see person details including custom fields, company affiliations (badges), notes, and tasks

#### Scenario: List People
- **WHEN** a user lists people
- **THEN** only people belonging to their current team are shown
- **AND** their associated companies are displayed as badges

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


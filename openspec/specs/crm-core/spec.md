# CRM Core Capability

## Purpose

Core CRM functionality for managing Companies, People, Opportunities, Tasks, and Notes with team-based multi-tenancy.
## Requirements
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

#### Scenario: Inline Create Company from Person
- **WHEN** a user creates or edits a person and inline-creates a company via the company field
- **THEN** the inline form uses the shared company form schema with visible Buyer and Supplier role checkboxes
- **AND** validation requires at least one role to be selected
- **AND** the created company is reachable via the Buyers and/or Suppliers list according to its roles

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


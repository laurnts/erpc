# CRM Core Delta

## MODIFIED Requirements

### Requirement: Task Management
The system SHALL provide CRUD operations for Tasks with polymorphic relationships.

#### Scenario: Create Task
- **WHEN** a user creates a task
- **THEN** the task is saved with optional assignments to companies or people

#### Scenario: Assign Task
- **WHEN** a user assigns people to a task
- **THEN** the many-to-many relationship is created via taskables table

#### Scenario: Complete Task
- **WHEN** a user marks a task as complete
- **THEN** the task status is updated

### Requirement: Note Management
The system SHALL provide CRUD operations for Notes with polymorphic relationships.

#### Scenario: Create Note
- **WHEN** a user creates a note on an entity (company or person)
- **THEN** the note is saved with the polymorphic noteable relationship

#### Scenario: View Notes
- **WHEN** a user views an entity
- **THEN** they see all associated notes in reverse chronological order

## REMOVED Requirements

### Requirement: Opportunity Management
**Reason**: Opportunities (CRM deals pipeline) are removed entirely. The ERP Request → Quote → Order workflow (`erp-trading-core`, `erp-quoting`, `erp-orders`) is the deal-tracking mechanism; the Opportunity entity was unreachable CRM inheritance holding only demo data.
**Migration**: The Opportunity model, Filament surfaces, importer/exporter, seeder fixtures, custom fields, and AI summary support are deleted; the `opportunities` table is dropped and opportunity-typed polymorphic rows are purged by an idempotent migration.

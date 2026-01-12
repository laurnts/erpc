# CRUD Flows Capability

## Purpose

Standard Create, Read, Update, Delete operation patterns using Filament resources.

## Requirements

### Requirement: Create Flow
The system SHALL support creating new records via modal slide-over forms.

#### Scenario: Create Action Trigger
- **WHEN** user clicks Create button in list header
- **THEN** a slide-over modal opens with the form

#### Scenario: Observer Hook on Create
- **WHEN** a record is created
- **THEN** Observer::creating() sets creator_id and team_id

#### Scenario: Post-Create Jobs
- **WHEN** a Company record is created
- **THEN** Observer::created() dispatches FetchFaviconForCompany job

#### Scenario: Custom Field Persistence
- **WHEN** a record with custom fields is created
- **THEN** custom field values are saved via UsesCustomFields trait

### Requirement: Read Flow
The system SHALL support viewing records via list and detail views.

#### Scenario: List Records
- **WHEN** user navigates to resource index
- **THEN** ListRecords page displays paginated, filtered, sorted records

#### Scenario: View Record
- **WHEN** user clicks on a record
- **THEN** ViewRecord page displays infolist with all fields

#### Scenario: Team Scoping
- **WHEN** records are queried
- **THEN** only records belonging to current team are returned

#### Scenario: Relation Managers
- **WHEN** viewing a record detail
- **THEN** RelationManagers display related entities (notes, tasks, people)

### Requirement: Update Flow
The system SHALL support editing records via modal slide-over forms.

#### Scenario: Edit Action Trigger
- **WHEN** user clicks Edit action on a record
- **THEN** form opens pre-filled with existing data

#### Scenario: Observer Hook on Update
- **WHEN** a record is saved
- **THEN** Observer::saved() invalidates AI summary cache

#### Scenario: Relation Sync
- **WHEN** many-to-many relationships are updated
- **THEN** pivot tables are synced automatically

### Requirement: Delete Flow
The system SHALL support soft-deleting and restoring records.

#### Scenario: Soft Delete
- **WHEN** user deletes a record
- **THEN** record is soft-deleted (deleted_at set)

#### Scenario: View Trashed
- **WHEN** TrashedFilter is applied
- **THEN** soft-deleted records become visible

#### Scenario: Restore Record
- **WHEN** user restores a soft-deleted record
- **THEN** deleted_at is set to null

#### Scenario: Force Delete
- **WHEN** user force-deletes a record
- **THEN** record is permanently removed from database

#### Scenario: Cascade Delete
- **WHEN** parent record is deleted
- **THEN** related records follow foreign key cascade rules

### Requirement: Bulk Operations
The system SHALL support bulk actions on multiple records.

#### Scenario: Bulk Delete
- **WHEN** user selects multiple records and clicks Delete
- **THEN** all selected records are soft-deleted

#### Scenario: Bulk Restore
- **WHEN** user selects trashed records and clicks Restore
- **THEN** all selected records are restored

### Requirement: Nested CRUD via Relation Managers
The system SHALL support CRUD operations on related records.

#### Scenario: Create Related Record
- **WHEN** user clicks Create in a RelationManager
- **THEN** new record is created with parent relationship set

#### Scenario: Attach Existing Record
- **WHEN** using AttachAction
- **THEN** existing record is linked via pivot table

#### Scenario: Detach Record
- **WHEN** using DetachAction
- **THEN** pivot relationship is removed (record not deleted)


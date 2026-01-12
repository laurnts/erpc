# Custom Fields Capability

## Purpose

Extensible no-code custom fields system for all CRM entities, enabling administrators to add custom data fields without code changes.

## Requirements

### Requirement: Custom Field Configuration
The system SHALL allow administrators to configure custom fields for any entity without code changes.

#### Scenario: Create Custom Field
- **WHEN** an admin creates a custom field for an entity type
- **THEN** the field is available for data entry on all instances of that entity

#### Scenario: Configure Field Type
- **WHEN** an admin configures a custom field
- **THEN** they can choose from supported field types (text, number, date, select, etc.)

#### Scenario: Set Field Options
- **WHEN** an admin creates a select-type custom field
- **THEN** they can configure options with labels and optional colors

### Requirement: Custom Field Data Entry
The system SHALL store and retrieve custom field values for entity instances.

#### Scenario: Enter Custom Field Value
- **WHEN** a user enters a value for a custom field
- **THEN** the value is stored and associated with the entity instance

#### Scenario: Display Custom Field Values
- **WHEN** a user views an entity
- **THEN** custom field values are displayed with appropriate formatting

#### Scenario: Filter by Custom Fields
- **WHEN** a user filters a list by custom field values
- **THEN** only matching entities are returned

### Requirement: Team-Scoped Custom Fields
The system SHALL scope custom fields to teams for multi-tenant isolation.

#### Scenario: Create Team Custom Fields
- **WHEN** a team is created
- **THEN** default custom fields are initialized via CreateTeamCustomFields listener

#### Scenario: Isolate Custom Field Definitions
- **WHEN** a team creates custom fields
- **THEN** only that team sees those field definitions


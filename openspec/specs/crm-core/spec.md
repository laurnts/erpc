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


# CRM Core Delta

## MODIFIED Requirements

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

## REMOVED Requirements

### Requirement: Company Management
**Reason**: The standalone Companies resource is retired. Company records are managed exclusively through the role-filtered Buyers and Suppliers views (see `erp-trading-core` — Buyers Entity, Suppliers Entity, and the new Company Role Classification requirement). CRUD, team scoping, people assignment via the `company_people` pivot, inline person creation, and soft-delete/restore all continue to apply through those views.
**Migration**: `CompanyResource` and its pages are deleted; references retarget to Buyer/Supplier views; existing role-less demo companies are cleaned up; every company creation path now requires at least one role.

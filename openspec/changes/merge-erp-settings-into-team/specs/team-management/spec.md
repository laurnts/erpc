# Spec Delta: Team Management

## ADDED Requirements

### Requirement: Team ERP Settings
The system SHALL allow team owners to configure ERP-specific settings per team.

#### Scenario: View Team ERP Settings
- **GIVEN** a user is the owner or admin of a team
- **WHEN** they navigate to team settings
- **THEN** they see the ERP Configuration section with current values

#### Scenario: Update Team ERP Settings
- **GIVEN** a user is the owner or admin of a team
- **WHEN** they modify ERP settings and save
- **THEN** the settings are persisted to the team's `erp_settings` JSON column
- **AND** a success notification is displayed

#### Scenario: Default ERP Settings
- **GIVEN** a team has no ERP settings configured
- **WHEN** ERP settings are accessed
- **THEN** sensible defaults are returned (USD currency, 11% tax, 30-day terms, standard prefixes)

#### Scenario: ERP Settings Isolation
- **GIVEN** two teams with different ERP configurations
- **WHEN** each team generates documents (quotes, orders, invoices)
- **THEN** each uses its own team's settings (currency, prefixes, company info)

## MODIFIED Requirements

### Requirement: Team Creation
The system SHALL allow users to create and manage teams.

#### Scenario: Create Team (MODIFIED)
- **WHEN** a user creates a team with a valid name
- **THEN** the team is created with the user as owner
- **AND** the team has default ERP settings initialized

## REMOVED Requirements

(None - the global ErpSettings page is removed but was not part of the team-management spec)

# Team Management Capability

## Purpose

Multi-team workspace management with memberships, invitations, and role-based access control.

## Requirements

### Requirement: Team Creation
The system SHALL allow users to create and manage teams.

#### Scenario: Create Team
- **WHEN** a user creates a team with a valid name
- **THEN** the team is created with the user as owner
- **AND** the team has default ERP settings initialized

#### Scenario: Personal Team
- **WHEN** a new user registers
- **THEN** a personal team is automatically created via CreatePersonalTeamListener

### Requirement: Team Membership
The system SHALL manage team memberships with role-based permissions.

#### Scenario: Add Team Member
- **WHEN** a team owner adds a member
- **THEN** the membership is created with specified role

#### Scenario: Remove Team Member
- **WHEN** a team owner removes a member
- **THEN** the membership is deleted and access is revoked

#### Scenario: Update Member Role
- **WHEN** a team owner changes a member's role
- **THEN** the role is updated and permissions are adjusted

### Requirement: Team Invitations
The system SHALL support email-based team invitations.

#### Scenario: Invite Team Member
- **WHEN** a team owner invites a user via email
- **THEN** an invitation is created and email is sent

#### Scenario: Accept Invitation
- **WHEN** a user accepts an invitation via signed URL
- **THEN** they are added to the team with the invited role

#### Scenario: Cancel Invitation
- **WHEN** a team owner cancels a pending invitation
- **THEN** the invitation is deleted and cannot be accepted

### Requirement: Team Switching
The system SHALL allow users to switch between their teams.

#### Scenario: Switch Team
- **WHEN** a user switches to another team they belong to
- **THEN** their current team context is updated via SwitchTeam listener

#### Scenario: Team Context Isolation
- **WHEN** a user is working in a team context
- **THEN** they only see data belonging to that team (TeamScope)

### Requirement: Team Deletion
The system SHALL allow team deletion with proper cleanup.

#### Scenario: Delete Team
- **WHEN** a team owner deletes the team
- **THEN** all team data, memberships, and invitations are removed

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


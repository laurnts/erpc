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
- **AND** if role is `central_purchasing`, a sub-role (Key Account, Dept. Head of Sales, Deputy Director, or Director) MUST be selected

#### Scenario: Remove Team Member
- **WHEN** a team owner removes a member
- **THEN** the membership is deleted and access is revoked

#### Scenario: Update Member Role
- **WHEN** a team owner changes a member's role
- **THEN** the role is updated and permissions are adjusted
- **AND** if role is changed to `central_purchasing`, a sub-role MUST be selected
- **AND** if role is changed from `central_purchasing` to another role, the sub-role AND approver flag are cleared
- **AND** if the sub-role is not Finance, the approver flag is cleared
- **AND** all role updates are performed through a single shared action so no write path can skip the cleanup rules

#### Scenario: Central Purchasing Role Assignment
- **WHEN** a team member is assigned the Central Purchasing role
- **THEN** they must select one of four sub-roles: Key Account, Dept. Head of Sales, Deputy Director, or Director
- **AND** the sub-role is stored in the `central_purchasing_role` column
- **AND** the member has read, create, and update permissions (same as Editor role)

#### Scenario: Leave Team
- **WHEN** a non-owner member chooses to leave the team from their own row on the Team Members page
- **THEN** their membership is deleted and they are redirected to the panel home
- **AND** the leave action is only visible on the authenticated user's own membership row

#### Scenario: Owner Cannot Leave
- **WHEN** the team owner attempts to be removed or to leave
- **THEN** the operation is rejected

### Requirement: Team Invitations
The system SHALL support email-based team invitations.

#### Scenario: Invite Team Member
- **WHEN** a team owner invites a user via email
- **THEN** an invitation is created and email is sent
- **AND** if role is `central_purchasing`, the selected sub-role is stored on the invitation

#### Scenario: Accept Invitation
- **WHEN** a user accepts an invitation via signed URL
- **THEN** they are added to the team with the invited role
- **AND** the invitation's Central Purchasing sub-role (if any) is copied to the membership

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

### Requirement: Central Purchasing Role Selection
The system SHALL provide a Central Purchasing role option for team members with sub-role selection.

#### Scenario: Display Central Purchasing Role Option
- **WHEN** a user views the role selection interface (Add Team Member or Edit Member)
- **THEN** they see three role options: Administrator, Editor, and Central Purchasing
- **AND** each role displays its description

#### Scenario: Show Sub-Role Selection
- **WHEN** a user selects Central Purchasing as the role
- **THEN** a select dropdown appears below the role selection
- **AND** the dropdown contains four options: Key Account, Dept. Head of Sales, Deputy Director, Director
- **AND** the sub-role selection is required

#### Scenario: Hide Sub-Role Selection
- **WHEN** a user selects Administrator or Editor as the role
- **THEN** the sub-role selection dropdown is hidden
- **AND** any previously selected sub-role value is cleared

#### Scenario: Validate Sub-Role Requirement
- **WHEN** a user attempts to save a team member with Central Purchasing role
- **AND** no sub-role is selected
- **THEN** validation fails with an error message
- **AND** the form submission is prevented

#### Scenario: Display Central Purchasing Sub-Role
- **WHEN** a user views a team member with Central Purchasing role
- **THEN** the member's role is displayed as "Central Purchasing"
- **AND** the sub-role is displayed in the member detail view (e.g., "Central Purchasing - Key Account")

### Requirement: Single Member Management Surface
The system SHALL provide team member management exclusively through the Team Members page; the team profile page SHALL contain only team-level settings.

#### Scenario: Team Members page capabilities
- **WHEN** a user opens the Team Members page from the sidebar
- **THEN** they can view members, invite members, see pending invitations, edit member roles, remove members (owner only), and leave the team (own row only), subject to their permissions

#### Scenario: Edit Team page scope
- **WHEN** a user opens the Edit Team tenant profile page
- **THEN** they see only team settings (team name, company information, branding, delete team)
- **AND** no member management sections are rendered

### Requirement: Team Owner Visibility
The system SHALL display the team owner on the Team Members page even though the owner has no membership record.

#### Scenario: Owner card
- **WHEN** a user views the Team Members page
- **THEN** the team owner is displayed above the members table with an Owner badge
- **AND** the owner entry offers no edit, remove, or leave actions

### Requirement: Member Credential Protection
The system SHALL NOT allow team administrators to change a member's login credentials through team member management.

#### Scenario: Edit form excludes credentials
- **WHEN** a team owner edits a member on the Team Members page
- **THEN** the form offers team-scoped fields (role, sub-role, approver flag) and profile basics (name, photo)
- **AND** the member's login email and password cannot be changed there


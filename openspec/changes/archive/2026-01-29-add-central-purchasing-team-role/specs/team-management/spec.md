## MODIFIED Requirements

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
- **AND** if role is changed from `central_purchasing` to another role, the sub-role is cleared

#### Scenario: Central Purchasing Role Assignment
- **WHEN** a team member is assigned the Central Purchasing role
- **THEN** they must select one of four sub-roles: Key Account, Dept. Head of Sales, Deputy Director, or Director
- **AND** the sub-role is stored in the `central_purchasing_role` column
- **AND** the member has read, create, and update permissions (same as Editor role)

## ADDED Requirements

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

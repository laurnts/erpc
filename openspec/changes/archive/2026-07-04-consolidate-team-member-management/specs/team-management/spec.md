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

## ADDED Requirements

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

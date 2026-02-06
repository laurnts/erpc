## ADDED Requirements

### Requirement: Buyer Assignment for Key Account Team Members
The system SHALL allow assigning buyers to key account team members, restricting which key accounts can handle which buyers.

#### Scenario: View buyer assignments for key account
- **WHEN** viewing a team member with Key Account role
- **THEN** a "Buyers" section appears below "Member Information" and "Team Details" sections
- **AND** the section displays a table of buyers assigned to this key account
- **AND** the table shows buyer code, name, and active status

#### Scenario: Assign buyer to key account
- **WHEN** an admin clicks "Add Buyer" in the Buyers relation manager for a key account
- **THEN** a modal opens with a buyer selection dropdown
- **AND** only buyers (`is_buyer = true`) are shown in the dropdown
- **AND** upon selection and save, the buyer is assigned to the key account
- **AND** the assignment is stored in the `key_account_buyers` pivot table

#### Scenario: Remove buyer assignment from key account
- **WHEN** an admin clicks detach action for a buyer in the Buyers relation manager
- **THEN** the buyer assignment is removed from the key account
- **AND** the key account will no longer appear in "Prepared By" dropdowns for that buyer's requests

#### Scenario: Buyer assignment section visibility
- **WHEN** viewing a team member without Key Account role
- **THEN** the Buyers relation manager section is not displayed
- **AND** buyer assignment is only available for team members with `central_purchasing_role = KEY_ACCOUNT`

## MODIFIED Requirements

### Requirement: Team Membership
The system SHALL manage team memberships with role-based permissions.

#### Scenario: Add Team Member
- **WHEN** a team owner adds a member
- **THEN** the membership is created with specified role
- **AND** if role is `central_purchasing`, a sub-role (Key Account, Dept. Head of Sales, Deputy Director, or Director) MUST be selected
- **AND** if sub-role is Key Account, buyers can be assigned to the member via the Buyers relation manager

#### Scenario: Update Member Role
- **WHEN** a team owner changes a member's role
- **THEN** the role is updated and permissions are adjusted
- **AND** if role is changed to `central_purchasing`, a sub-role MUST be selected
- **AND** if role is changed from `central_purchasing` to another role, the sub-role is cleared
- **AND** if sub-role is changed from Key Account to another role, buyer assignments are preserved but Buyers relation manager is hidden

## ADDED Requirements

### Requirement: Member Resource Management
The system SHALL provide a dedicated Filament resource for managing team members with listing, viewing, adding, editing, and removing capabilities.

#### Scenario: View Team Members List
- **GIVEN** a user belongs to a team
- **WHEN** they navigate to the Member menu item
- **THEN** they see a list of all team members
- **AND** each member shows their name, email, and role
- **AND** they can search and filter members

#### Scenario: View Individual Member Actions
- **GIVEN** a user is viewing the member list
- **WHEN** they see a member in the list
- **THEN** they can see member email and role
- **AND** they can click on role to update it (if they have permission)
- **AND** they can click remove action to remove the member (if they have permission)

#### Scenario: Add Team Member via Invitation
- **GIVEN** a user has permission to add team members
- **WHEN** they click "Add Team Member" button
- **THEN** a form is displayed with:
  - Email field (required, email validation)
  - Role selection (Radio buttons: Administrator or Editor)
  - Role descriptions displayed below each option
- **WHEN** they enter an email and select a role (Administrator or Editor)
- **AND** submit the form
- **THEN** an invitation is sent to the email address
- **AND** the invitation is created with the selected role
- **AND** a success notification is displayed

#### Scenario: Add Team Member Directly
- **GIVEN** a user has permission to add team members
- **WHEN** they add a team member with an email of an existing registered user
- **THEN** the user is added directly to the team with the selected role
- **AND** no invitation is sent

#### Scenario: Edit Member Role
- **GIVEN** a user is viewing the member list
- **AND** they have permission to update team members
- **WHEN** they click on a member's role label
- **THEN** a modal dialog is displayed with:
  - Role selection (Radio buttons: Administrator or Editor)
  - Role descriptions displayed below each option:
    - Administrator: "Administrator users can perform any action."
    - Editor: "Editor users have the ability to read, create, and update."
  - Current role is pre-selected
  - Save button
- **WHEN** they change the role and save
- **THEN** the member's role is updated
- **AND** permissions are adjusted accordingly
- **AND** a success notification is displayed
- **AND** the modal closes

#### Scenario: Remove Team Member
- **GIVEN** a user is viewing a member's detail page
- **AND** they have permission to remove team members
- **WHEN** they click Remove action
- **AND** confirm the removal
- **THEN** the member is removed from the team
- **AND** their access is revoked
- **AND** they are redirected to the member list

#### Scenario: View Pending Invitations (Administrator Only)
- **GIVEN** a user is an administrator of the team
- **WHEN** they view the member list page
- **THEN** they see a "Pending Team Invitations" section below the member list
- **AND** the section shows all pending invitations with email and role
- **AND** they can resend or cancel invitations

#### Scenario: Pending Invitations Hidden (Non-Administrator)
- **GIVEN** a user is not an administrator of the team
- **WHEN** they view the member list page
- **THEN** the "Pending Team Invitations" section is not visible

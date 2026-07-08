# Supplier Portal — unify-portal-membership-lifecycle deltas

## MODIFIED Requirements

### Requirement: Supplier Portal Access via Invitations
The system SHALL grant supplier portal access exclusively through admin-issued invitations, reusing the shared portal invitation flow (one invite action, one invitation mail, one acceptance transaction) with `portal = supplier`; there is no supplier self-registration. Each supplier portal person is represented by a single membership record whose lifecycle state — Invited, Active, or Deactivated — is shown in a staff-facing Portal Users list on the supplier record, with the same state transitions as the customer portal.

#### Scenario: Invite a supplier contact
- **WHEN** an admin invites a contact email from a supplier company record
- **THEN** an invitation with `portal = supplier` is sent with a signed acceptance URL targeting the supplier panel
- **AND** acceptance links or creates a `User`, marks the email verified, and activates the Invited membership record — no duplicate record is created
- **AND** the invitation is refused with a validation error if the company does not have `is_supplier = true`
- **AND** a membership record in the Invited state appears immediately in the supplier's Portal Users list

#### Scenario: Staff can see and manage supplier portal users
- **WHEN** staff open a supplier company record
- **THEN** a Portal Users list shows every supplier portal membership with its Invited, Active, or Deactivated state
- **AND** Invited memberships can be revoked, Active ones deactivated, and Deactivated ones reactivated from that list

#### Scenario: Invitation tokens resolve only within their own portal
- **WHEN** a `portal = customer` invitation token is opened on the supplier panel's acceptance URL
- **THEN** the page responds 404 and the invitation cannot be accepted there

#### Scenario: Deactivate supplier access
- **WHEN** an admin deactivates a supplier user's portal access
- **THEN** the user loses supplier panel access for that company, with force-logout on the next request
- **AND** the membership remains listed in the Deactivated state and can be reactivated

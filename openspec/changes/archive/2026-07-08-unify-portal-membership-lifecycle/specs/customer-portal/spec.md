# Customer Portal — unify-portal-membership-lifecycle deltas

## MODIFIED Requirements

### Requirement: Customer Portal User Access
The system SHALL link authenticated users to buyer companies via portal access records, scoped to a trading team and typed per portal, so that customer-portal capability requires an explicit customer-portal membership and cannot be derived from company role flags alone. Invitation issuance and acceptance run through the shared portal invitation flow (one invite action, one invitation mail, one acceptance transaction), parameterized by portal type. Each portal person is represented by a single membership record whose lifecycle state — Invited, Active, or Deactivated — is derived from the record (no user linked yet; user linked and active; user linked and inactive) and shown in one staff-facing Portal Users list.

#### Scenario: Admin invites portal user
- **WHEN** an admin invites a contact email from a buyer company record
- **THEN** an invitation email is sent with a signed acceptance URL
- **AND** the invitation is scoped to that buyer company and team
- **AND** the invitation records `portal = customer`
- **AND** the invitation is refused with a validation error if the company does not have `is_buyer = true`
- **AND** a membership record in the Invited state appears immediately in the buyer's Portal Users list

#### Scenario: Invited state grants no access
- **WHEN** a membership record is in the Invited state
- **THEN** it grants no portal panel access on any portal

#### Scenario: Accept portal invitation
- **WHEN** the invitee accepts the invitation and sets a password
- **THEN** a `User` account is created or linked
- **AND** the existing Invited membership record is linked to the user and becomes Active — no duplicate record is created
- **AND** the user can log in to the customer panel

#### Scenario: Invitation tokens resolve only within their own portal
- **WHEN** a `portal = supplier` invitation token is opened on the customer panel's acceptance URL
- **THEN** the page responds 404 and the invitation cannot be accepted there

#### Scenario: Revoke a pending invitation
- **WHEN** an admin revokes an Invited membership from the Portal Users list
- **THEN** the membership record and its invitation are removed and the acceptance link stops working
- **AND** Active and Deactivated memberships cannot be revoked, only deactivated or reactivated

#### Scenario: Deactivate portal access
- **WHEN** an admin deactivates a user's portal access for a buyer company
- **THEN** the membership state becomes Deactivated and the user can no longer view that company's requests in the portal
- **AND** existing sessions for that company are invalidated on next request
- **AND** the admin can later reactivate the same membership from the same list

#### Scenario: Multiple buyer companies
- **WHEN** a portal user has access to more than one buyer company
- **THEN** the portal displays a company switcher
- **AND** all request queries are scoped to the selected company
- **AND** the switcher lists only companies where the user holds an Active `portal = customer` membership and the company has `is_buyer = true`

#### Scenario: Access requires portal-typed membership and matching company role
- **WHEN** customer panel access is evaluated for a user
- **THEN** access is granted only if the user has a verified email, an Active `company_portal_users` record with `portal = customer`, and that company has `is_buyer = true`
- **AND** a membership for a company with only `is_supplier = true` does not grant customer panel access

#### Scenario: Dual-role company requires explicit membership per portal
- **WHEN** a person is invited as a customer portal user of a company that is both buyer and supplier
- **THEN** they gain customer portal capability only
- **AND** supplier portal capability requires a separate explicit supplier-portal invitation and membership

#### Scenario: Registration-sourced members appear in the same list
- **WHEN** a portal membership is created by catalog registration approval rather than an invitation
- **THEN** it appears in the same Portal Users list in the Active state

#### Scenario: Existing pending invitations are backfilled
- **WHEN** the unified lifecycle is introduced
- **THEN** every pending invitation gains an Invited membership record
- **AND** in-flight invitation acceptance continues to work unchanged

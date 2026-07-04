# Customer Portal — dedupe-portal-shells deltas

## MODIFIED Requirements

### Requirement: Customer Portal User Access
The system SHALL link authenticated users to buyer companies via portal access records, scoped to a trading team and typed per portal, so that customer-portal capability requires an explicit customer-portal membership and cannot be derived from company role flags alone. Invitation issuance and acceptance run through the shared portal invitation flow (one invite action, one invitation mail, one acceptance transaction), parameterized by portal type.

#### Scenario: Admin invites portal user
- **WHEN** an admin invites a contact email from a buyer company record
- **THEN** an invitation email is sent with a signed acceptance URL
- **AND** the invitation is scoped to that buyer company and team
- **AND** the invitation records `portal = customer`
- **AND** the invitation is refused with a validation error if the company does not have `is_buyer = true`

#### Scenario: Accept portal invitation
- **WHEN** the invitee accepts the invitation and sets a password
- **THEN** a `User` account is created or linked
- **AND** a `company_portal_users` record is created with `is_active = true` and `portal` copied from the invitation
- **AND** the user can log in to the customer panel

#### Scenario: Invitation tokens resolve only within their own portal
- **WHEN** a `portal = supplier` invitation token is opened on the customer panel's acceptance URL
- **THEN** the page responds 404 and the invitation cannot be accepted there

#### Scenario: Deactivate portal access
- **WHEN** an admin deactivates a user's portal access for a buyer company
- **THEN** the user can no longer view that company's requests in the portal
- **AND** existing sessions for that company are invalidated on next request

#### Scenario: Multiple buyer companies
- **WHEN** a portal user has access to more than one buyer company
- **THEN** the portal displays a company switcher
- **AND** all request queries are scoped to the selected company
- **AND** the switcher lists only companies where the user holds an active `portal = customer` membership and the company has `is_buyer = true`

#### Scenario: Access requires portal-typed membership and matching company role
- **WHEN** customer panel access is evaluated for a user
- **THEN** access is granted only if the user has a verified email, an active `company_portal_users` row with `portal = customer`, and that company has `is_buyer = true`
- **AND** a membership for a company with only `is_supplier = true` does not grant customer panel access

#### Scenario: Dual-role company requires explicit membership per portal
- **WHEN** a person is invited as a customer portal user of a company that is both buyer and supplier
- **THEN** they gain customer portal capability only
- **AND** supplier portal capability requires a separate explicit supplier-portal invitation and membership

#### Scenario: Existing memberships are preserved
- **WHEN** the `portal` column is introduced
- **THEN** all existing memberships and pending invitations are backfilled as `portal = customer`
- **AND** in-flight invitation acceptance continues to work unchanged

### Requirement: Customer Portal Branding
The system SHALL apply the trading team's branding to the customer portal when configured, rendered through the shared portal shell so both portals present branding identically.

#### Scenario: Team logo on portal
- **WHEN** a team has a custom logo configured in team settings
- **THEN** the customer portal displays that logo
- **AND** falls back to default branding when no logo is set

#### Scenario: Team favicon on portal
- **WHEN** a team has a custom favicon configured
- **THEN** the customer portal uses that favicon

#### Scenario: Branding is rendered by the shared portal shell
- **WHEN** the customer panel renders its brand name, logo, or favicon
- **THEN** the values are resolved by the shared portal shell from the active portal context's company and team
- **AND** the logo view is the portal-shared Blade view, not a panel-specific copy

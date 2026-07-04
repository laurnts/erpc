# Supplier Portal — dedupe-portal-shells deltas

## MODIFIED Requirements

### Requirement: Supplier Portal Panel
The system SHALL provide a dedicated Filament panel for supplier self-service at a configurable path (default: `/supplier`), separate from the internal `app` panel and the buyer `customer` panel, structurally mirroring the customer portal (dedicated guard, session isolation, portal-typed membership, strict authorization) and composed from the shared portal shell rather than duplicated panel configuration.

#### Scenario: Supplier login URL
- **WHEN** the supplier panel is enabled
- **THEN** the supplier login page is available at `{APP_URL}/supplier/login`

#### Scenario: Panel disabled by configuration
- **WHEN** `supplier_portal_enabled` is false
- **THEN** supplier panel routes return 404 or a maintenance response and other panels are unaffected

#### Scenario: Panel access control
- **WHEN** supplier panel access is evaluated
- **THEN** access requires a verified email and an active `company_portal_users` row with `portal = supplier` for a company with `is_supplier = true`
- **AND** a customer-portal membership grants no supplier panel access, and vice versa — including for one person at a dual-role company

#### Scenario: Branding is rendered by the shared portal shell
- **WHEN** the supplier panel renders its brand name, logo, or favicon
- **THEN** the values are resolved by the shared portal shell from the active portal context's company and team
- **AND** the logo view is the portal-shared Blade view, not a customer-namespaced copy

### Requirement: Supplier Portal Access via Invitations
The system SHALL grant supplier portal access exclusively through admin-issued invitations, reusing the shared portal invitation flow (one invite action, one invitation mail, one acceptance transaction) with `portal = supplier`; there is no supplier self-registration.

#### Scenario: Invite a supplier contact
- **WHEN** an admin invites a contact email from a supplier company record
- **THEN** an invitation with `portal = supplier` is sent with a signed acceptance URL targeting the supplier panel
- **AND** acceptance creates or links a `User`, marks the email verified, and creates an active `portal = supplier` membership
- **AND** the invitation is refused with a validation error if the company does not have `is_supplier = true`

#### Scenario: Invitation tokens resolve only within their own portal
- **WHEN** a `portal = customer` invitation token is opened on the supplier panel's acceptance URL
- **THEN** the page responds 404 and the invitation cannot be accepted there

#### Scenario: Deactivate supplier access
- **WHEN** an admin deactivates a supplier user's portal access
- **THEN** the user loses supplier panel access for that company, with force-logout on the next request

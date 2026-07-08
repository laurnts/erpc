## RENAMED Requirements
- FROM: `### Requirement: Customer Portal Authentication`
- TO: `### Requirement: Buyer Portal Authentication`

## MODIFIED Requirements

### Requirement: Buyer Portal Authentication
The system SHALL provide separate panel access for buyer portal users versus internal staff users.

#### Scenario: Buyer accesses buyer panel
- **WHEN** a user with active `company_portal_users` record logs in at `/buyer/login`
- **THEN** they are authenticated to the `buyer` Filament panel via the `buyer` guard
- **AND** they are redirected to the buyer dashboard or requests list

#### Scenario: Buyer denied internal panel
- **WHEN** a user who only has portal access (no internal team membership) attempts to access the `app` panel
- **THEN** access is denied via `canAccessPanel()`
- **AND** they are not shown internal ERP navigation

#### Scenario: Internal staff denied buyer panel
- **WHEN** an internal staff user without portal access attempts to access the `buyer` panel
- **THEN** access is denied via `canAccessPanel()`
- **UNLESS** they also have an active `company_portal_users` record (dual-access exception)

#### Scenario: Buyer password reset
- **WHEN** a portal user requests a password reset from `/buyer/forgot-password`
- **THEN** a reset link is sent to their email
- **AND** the reset flow completes within the buyer panel context

#### Scenario: Buyer email verification
- **WHEN** a new portal user account is created via invitation
- **THEN** email verification is required before full portal access
- **AND** the verification link routes within the buyer panel

## ADDED Requirements

### Requirement: Concurrent Multi-Panel Sessions
The system SHALL give each Filament panel an isolated, explicitly-named session cookie so that the staff, buyer, and supplier panels can hold independent authenticated sessions in a single browser at the same time, across multiple tabs, on the same host.

#### Scenario: Distinct cookie per panel
- **WHEN** the application resolves the session cookie for a request
- **THEN** the `app` (staff) panel uses `erpc_staff_session`
- **AND** the buyer panel uses `erpc_buyer_session`
- **AND** the supplier panel uses `erpc_supplier_session`

#### Scenario: Concurrent logins do not clobber each other
- **WHEN** a user is authenticated to the staff panel in one tab and logs into the buyer panel in another tab of the same browser
- **THEN** both sessions remain valid and independently authenticated
- **AND** logging out of one panel does not end the session on another panel

#### Scenario: Livewire background requests use the originating panel's cookie
- **WHEN** a Livewire update request is issued from a buyer panel page
- **THEN** the request is resolved to the buyer session cookie (via referer, then the Livewire snapshot path)
- **AND** it does not read or write the staff or supplier session

#### Scenario: Staff panel is the default fall-through
- **WHEN** a request targets neither the buyer nor the supplier panel path
- **THEN** the default `erpc_staff_session` cookie is used
- **AND** no panel-specific override is applied

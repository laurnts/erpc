# Authentication Capability

## Purpose

User authentication including local login, social OAuth, two-factor authentication, and API tokens.
## Requirements
### Requirement: Social OAuth Authentication
The system SHALL support OAuth authentication via multiple providers.

#### Scenario: OAuth Redirect
- **WHEN** a user clicks a social login button
- **THEN** they are redirected to the provider for authentication (rate limited: 10/min)

#### Scenario: OAuth Callback
- **WHEN** a user returns from OAuth provider with valid credentials
- **THEN** they are authenticated or a new user is created via CreateNewSocialUser

#### Scenario: Link Social Account
- **WHEN** an existing user authenticates via a new social provider
- **THEN** the social account is linked to their existing user

### Requirement: Two-Factor Authentication
The system SHALL support optional two-factor authentication for enhanced security.

#### Scenario: Enable 2FA
- **WHEN** a user enables two-factor authentication
- **THEN** they receive recovery codes and must verify via OTP

#### Scenario: Authenticate with 2FA
- **WHEN** a user with 2FA enabled logs in
- **THEN** they must provide a valid OTP or recovery code

#### Scenario: Disable 2FA
- **WHEN** a user disables two-factor authentication
- **THEN** 2FA is removed from their account

### Requirement: API Token Authentication
The system SHALL support Sanctum-based API token authentication.

#### Scenario: Create API Token
- **WHEN** a user creates an API token with specific abilities
- **THEN** the token is generated and displayed once

#### Scenario: Use API Token
- **WHEN** a request includes a valid Bearer token
- **THEN** the request is authenticated as that user

#### Scenario: Revoke API Token
- **WHEN** a user deletes an API token
- **THEN** the token is invalidated and cannot be used

### Requirement: Session Management
The system SHALL allow users to manage their active sessions.

#### Scenario: View Sessions
- **WHEN** a user views their profile
- **THEN** they see all active browser sessions

#### Scenario: Logout Other Sessions
- **WHEN** a user logs out other sessions
- **THEN** all sessions except the current one are invalidated

#### Scenario: Separate panel sessions
- **WHEN** a user is logged into the customer panel
- **THEN** their session is independent of the internal `app` panel session
- **AND** logging out of one panel does not automatically log out of the other

### Requirement: Password Management
The system SHALL support secure password management.

#### Scenario: Update Password
- **WHEN** a user updates their password with valid current password
- **THEN** the password is hashed and stored

#### Scenario: Reset Password
- **WHEN** a user requests a password reset
- **THEN** a reset link is sent to their email (external redirect)

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


# authentication Specification Delta

## ADDED Requirements

### Requirement: Customer Portal Authentication
The system SHALL provide separate panel access for customer (buyer portal) users versus internal staff users.

#### Scenario: Customer accesses customer panel
- **WHEN** a user with active `company_portal_users` record logs in at `/customer/login`
- **THEN** they are authenticated to the `customer` Filament panel
- **AND** they are redirected to the customer dashboard or requests list

#### Scenario: Customer denied internal panel
- **WHEN** a user who only has portal access (no internal team membership) attempts to access the `app` panel
- **THEN** access is denied via `canAccessPanel()`
- **AND** they are not shown internal ERP navigation

#### Scenario: Internal staff denied customer panel
- **WHEN** an internal staff user without portal access attempts to access the `customer` panel
- **THEN** access is denied via `canAccessPanel()`
- **UNLESS** they also have an active `company_portal_users` record (dual-access exception)

#### Scenario: Customer password reset
- **WHEN** a portal user requests a password reset from `/customer/forgot-password`
- **THEN** a reset link is sent to their email
- **AND** the reset flow completes within the customer panel context

#### Scenario: Customer email verification
- **WHEN** a new portal user account is created via invitation
- **THEN** email verification is required before full portal access
- **AND** the verification link routes within the customer panel

## MODIFIED Requirements

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

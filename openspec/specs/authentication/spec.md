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

### Requirement: Password Management
The system SHALL support secure password management.

#### Scenario: Update Password
- **WHEN** a user updates their password with valid current password
- **THEN** the password is hashed and stored

#### Scenario: Reset Password
- **WHEN** a user requests a password reset
- **THEN** a reset link is sent to their email (external redirect)


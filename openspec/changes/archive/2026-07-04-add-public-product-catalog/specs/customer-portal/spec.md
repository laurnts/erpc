# customer-portal Delta

## ADDED Requirements

### Requirement: Buyer Self-Registration with Approval
The system SHALL allow visitors to apply for customer portal access via a public registration form, and SHALL require internal approval before the applicant can sign in or submit anything. Applications SHALL NOT create User, Company, or portal-access records until approved.

#### Scenario: Visitor applies
- **WHEN** a visitor submits the registration form (name, email, company name, password; optional phone/message)
- **THEN** a `portal_registration_requests` row is created with status `pending` and the password stored hashed
- **AND** no `User`, `Company`, or `CompanyPortalUser` records are created yet
- **AND** the visitor sees confirmation that the application awaits approval and cannot sign in meanwhile

#### Scenario: Duplicate application or existing account
- **WHEN** the submitted email already has a pending application or an existing user account
- **THEN** the form rejects the submission with a clear message

#### Scenario: Staff approves an application
- **WHEN** internal staff approve a pending application
- **THEN** a buyer `Company` (is_buyer=true), a `User` (reusing the stored password hash), and an active `CompanyPortalUser` with `portal = customer` are created for the catalog team
- **AND** the applicant completes an email-verification round-trip before first sign-in (the application email address was never verified)
- **AND** the applicant is notified by email that they can sign in to the customer portal
- **AND** the application status becomes `approved`

#### Scenario: Staff rejects an application
- **WHEN** internal staff reject a pending application
- **THEN** the status becomes `rejected`, no records are created, and the applicant is notified by email

#### Scenario: Pending applicant attempts sign-in
- **WHEN** an applicant whose application is pending or rejected tries to sign in to the customer portal
- **THEN** authentication fails as for any unknown credential (no account exists)

### Requirement: Catalog Quote Cart Submission
The system SHALL convert a submitted quote cart into a standard portal-originated Request for the portal user's active buyer company.

#### Scenario: Submit the cart
- **WHEN** an active portal user submits a quote cart with at least one line
- **THEN** a `Request` is created with `buyer_id` = the user's active portal company, `stage = draft`, `submission_method = catalog`, `submitted_by_user_id` = the portal user, and `submitted_at` = now
- **AND** one `RequestItem` is created per cart line with `article_id`, the chosen quantity, the article's unit, and a description derived from the article name
- **AND** the request appears in the user's portal request list and follows the existing portal-originated workflow and supplier-confidentiality rules

#### Scenario: Cart validation
- **WHEN** a cart line has a non-positive quantity or references an article that is no longer grid-visible
- **THEN** submission is rejected for that line with a message, without creating a partial Request

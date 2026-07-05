# customer-portal Specification

## Purpose
TBD - created by archiving change add-customer-portal. Update Purpose after archive.
## Requirements
### Requirement: Customer Portal Panel
The system SHALL provide a dedicated Filament panel for buyer (customer) self-service, accessible at a configurable path on the main application URL (default: `/customer`).

#### Scenario: Customer login URL
- **WHEN** `customer_path` is configured as `customer`
- **THEN** the customer login page is available at `{APP_URL}/customer/login`
- **AND** the panel is separate from the internal `app` panel

#### Scenario: Panel disabled by configuration
- **WHEN** `customer_portal_enabled` is `false`
- **THEN** the customer panel routes return 404 or maintenance response
- **AND** internal admin functionality is unaffected

#### Scenario: Staff login link
- **WHEN** a user views the customer login page
- **THEN** a link to the internal staff login (`app` panel) is displayed
- **AND** vice versa on the staff login page

---

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

### Requirement: Customer Request Submission — Manual
The system SHALL allow portal users to submit goods or service requests by manually entering request items.

#### Scenario: Create manual request
- **WHEN** a portal user submits a request with title, request type, and at least one item (description, quantity, unit of measure)
- **THEN** a `Request` record is created with `submission_method = manual`
- **AND** `submitted_at` is set to the current timestamp
- **AND** `buyer_id` is set to the user's active portal company (not user-selectable)
- **AND** `stage` defaults to `draft`
- **AND** `RequestItem` records are created for each entered line

#### Scenario: Optional project selection
- **WHEN** a portal user creates a request and the buyer has associated projects
- **THEN** the user may optionally select a project
- **AND** the request is linked via `project_id`

#### Scenario: Required delivery date
- **WHEN** a portal user sets a `required_by` date
- **THEN** the date is stored on the request
- **AND** it is visible to internal staff in the admin panel

---

### Requirement: Customer Request Submission — Document Upload
The system SHALL allow portal users to submit requests by uploading RFQ/PR documents instead of manual item entry.

#### Scenario: Create document-based request
- **WHEN** a portal user selects document submission and uploads at least one file (PDF, Excel, Word, or image)
- **THEN** a `Request` record is created with `submission_method = document`
- **AND** files are stored in the request `attachments` media collection
- **AND** `stage` defaults to `draft` pending staff review

#### Scenario: Document request without items
- **WHEN** a document-based request is submitted
- **THEN** the request may have zero `RequestItem` records initially
- **AND** internal staff are notified to review and add items

---

### Requirement: Customer Request Progress Tracking
The system SHALL allow portal users to view the progress of their submitted requests using customer-friendly status labels.

#### Scenario: List own requests
- **WHEN** a portal user views the requests list
- **THEN** only requests for their active buyer company are shown
- **AND** each row displays request number, title, customer status label, and submitted date

#### Scenario: View request detail
- **WHEN** a portal user opens a request detail page
- **THEN** they see request header, items (description, quantity, UOM), and progress timeline
- **AND** they do NOT see internal notes, supplier information, margins, QE, or PNL data

#### Scenario: Customer status mapping
- **WHEN** an internal request is in stage `awaiting_buyer_confirmation`
- **THEN** the portal displays a customer label such as "Menunggu Konfirmasi Anda"
- **AND** the internal stage name is not shown to the customer

#### Scenario: Edit draft request
- **WHEN** a portal request is in `draft` stage and has not been advanced by staff
- **THEN** the portal user may edit the request title, items, and required_by date
- **WHEN** staff advances the request beyond draft review
- **THEN** the portal user can no longer edit the request

---

### Requirement: Admin Visibility of Portal Requests
The system SHALL surface portal-submitted requests in the internal admin dashboard with clear identification.

#### Scenario: Portal badge on request list
- **WHEN** an internal user views the requests list
- **THEN** requests with `submission_method` set display a "From Portal" indicator

#### Scenario: Filter portal requests
- **WHEN** an internal user filters requests by submission source
- **THEN** they can filter to show only portal-submitted or only internal-created requests

#### Scenario: Notification on new portal submission
- **WHEN** a portal user submits a new request
- **THEN** relevant internal team members receive a notification
- **AND** the notification includes request number, buyer name, and submission method

---

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


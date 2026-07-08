# supplier-portal Specification

## Purpose
TBD - created by archiving change add-public-product-catalog. Update Purpose after archive.
## Requirements
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
- **AND** a buyer-portal membership grants no supplier panel access, and vice versa — including for one person at a dual-role company

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

### Requirement: Supplier Article Self-Service
The system SHALL let supplier portal users view the articles assigned to their company and update only their own per-link commercial fields; suppliers SHALL NOT create articles, delete links, or assign themselves to articles — listings are owned by the central purchasing team.

#### Scenario: View own listing
- **WHEN** a supplier user opens "My Articles"
- **THEN** only their own company's supplier-article links are listed
- **AND** article identity fields (name, code-free identification, unit) are read-only

#### Scenario: Update own offer
- **WHEN** a supplier user edits a row
- **THEN** exactly four fields are writable: `supplier_price`, its currency (defaulting to the company's default currency), `available_quantity`, `lead_time_days`
- **AND** the corresponding `*_updated_at` timestamps are stamped
- **AND** tampered payloads writing any other field (including `is_preferred`, `is_active`, `last_quoted_*`) are rejected by the action-level whitelist

#### Scenario: No listing management
- **WHEN** a supplier user looks for create, delete, or attach actions anywhere in the panel
- **THEN** none exist, and direct attempts are denied by policy
- **NOTE:** supplier-initiated listing requests (proposing a new article or assignment, subject to central purchasing approval) are a deliberate future capability, not part of this change

#### Scenario: Cross-supplier access denied
- **WHEN** a supplier user attempts to view or update another company's supplier-article row
- **THEN** the attempt is denied (query scope excludes it; policy denies it under strict authorization)

### Requirement: Supplier RFQ Participation
The system SHALL let supplier portal users see RFQs that were actually sent to them, submit their quotation per item, or decline — per the erp-quoting requirements RFQ Send Visibility Gate, Supplier Quote Portal Submission, and Supplier Quote Decline.

#### Scenario: Open RFQs list
- **WHEN** a supplier user opens "Quote Requests"
- **THEN** tabs show Open (sent, pending, undeclined), Submitted, Won, and Lost quotes — all scoped to their own company and to quotes with `sent_to_supplier_at` set

#### Scenario: View RFQ items
- **WHEN** a supplier user opens an RFQ
- **THEN** they see item descriptions, quantities, units, and notes (including read-only child rows of service quotes)
- **AND** no buyer identity, request context beyond the items, or other suppliers' data is shown

#### Scenario: Submit or decline
- **WHEN** the quote is sent, pending, undeclined, and its validity has not passed
- **THEN** the user can submit per-item prices with validity, notes, and a quotation document, or decline
- **AND** after submitting, the quote renders as "Submitted — under review" regardless of internal evaluation state

### Requirement: Supplier RFQ Outcome Visibility
The system SHALL reveal quote outcomes to suppliers only after the internal announcement (per erp-quoting RFQ Outcome Announcement), showing each supplier only their own result.

#### Scenario: Outcome after announcement
- **WHEN** outcomes are announced
- **THEN** the supplier's quote appears under Won (any own item selected) or Lost, and one outcome notification is received
- **AND** for split awards, item-level won/lost is shown via the supplier's own item selection flags only

#### Scenario: No pre-announcement leak
- **WHEN** internal staff toggle selections during an open evaluation
- **THEN** the portal continues to render "Submitted — under review" and no notification is sent

#### Scenario: Losers never see winners
- **WHEN** a supplier lost some or all items
- **THEN** the winner's identity and winning prices are never exposed

### Requirement: Supplier Portal Confidentiality
The system SHALL ensure the supplier portal exposes no buyer data, no other suppliers' data or existence, no internal evaluation or margin data, and no unsent solicitations.

#### Scenario: Confidential data never renders
- **WHEN** a supplier user browses any portal view or receives any portal response
- **THEN** none of the following are present: buyer identity or request context, other suppliers' quotes/prices/existence, the comparison matrix or evaluation snapshots, margins or article `list_price` linkage, profit-and-loss data, internal notes and notification metadata, and RFQs without `sent_to_supplier_at`

#### Scenario: Session and portal isolation
- **WHEN** a user holds sessions on multiple panels
- **THEN** supplier panel sessions are isolated (dedicated guard and session cookie), and losing membership force-logs-out on the next request


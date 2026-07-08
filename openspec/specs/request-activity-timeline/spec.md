# request-activity-timeline Specification

## Purpose
TBD - created by archiving change add-request-activity-timeline. Update Purpose after archive.
## Requirements
### Requirement: Request Activity Timeline

The system SHALL present, on the request detail page, a chronological timeline of what has happened to a request — audit changes, document uploads, and status/document milestones for the request and its logged child records — sourced read-time from the live activity log and rendered with actor, actor type, timestamp, and drill-in to the change detail.

#### Scenario: Staff sees an audited change on the timeline
- **WHEN** a staff user opens a request where an audited field changed on the request or a logged child record (e.g. a buyer quote's total or status)
- **THEN** the timeline shows an entry with the acting user, the record reference, the change summary, and the time, ordered chronologically among other entries
- **AND** once line-item capture is in place, line-level changes appear through the same mechanism without timeline changes

#### Scenario: Credit movements appear with balances
- **WHEN** a credit transaction is recorded for the request's buyer (e.g. credit used by an order, credit released by a payment, an approved limit change)
- **THEN** the internal timeline shows the movement with its amount, before→after balances, and a link to the causing record, sourced read-only from the credit ledger

#### Scenario: Uploads appear with their uploader
- **WHEN** a document has been attached to the request or a child record
- **THEN** the timeline shows an upload entry with the uploader and the uploader's actor type

#### Scenario: Milestones appear without a bespoke capture layer
- **WHEN** a supplier quote is sent, a purchase order is approved, an invoice is issued, or a payment is recorded
- **THEN** each appears as a timeline entry, derived from existing status/timestamp changes (with an explicit log only for the column-less supplier-quote-sent action)

### Requirement: Audience-Scoped Visibility

The system SHALL resolve timeline visibility through a single helper that maps a viewer **party** to an **additive** allow-list of subject types and entry types that party may load, plus redaction rules. Parties SHALL be identity-scoped: `staff`/`admin` (full internal history), `buyer:{companyId}`, and `supplier:{companyId}`. Non-staff surfaces SHALL build their feed by selecting only allow-listed data; they SHALL NOT load the internal feed and filter it. Every timeline surface SHALL obtain its data through this helper.

#### Scenario: Competing suppliers are isolated by identity
- **WHEN** a request involves supplier A (company 42) and supplier B (company 43), and supplier A views the timeline
- **THEN** the feed contains only supplier A's own subjects, and supplier B's quotes, prices, and references are physically not loaded

#### Scenario: Additive construction cannot leak unlisted data
- **WHEN** the buyer party resolves its allow-list
- **THEN** supplier cost/margin subjects, P&L, and quotation-evaluation subjects are never queried on the buyer code path (not merely filtered out afterward)

#### Scenario: Redaction scrubs visible entries
- **WHEN** a party-visible entry still carries internal-only fields (supplier cost, margin, staff causer name)
- **THEN** those fields are removed before the entry is rendered for a non-staff party

#### Scenario: Staff and admin see the full history
- **WHEN** a staff or admin user views the timeline
- **THEN** the helper returns the complete internal subject and entry set with no redaction

### Requirement: Complete Capture Coverage

Every request-scoped child record type that the internal timeline enumerates SHALL have an activity-capture path, and the absence of one SHALL fail an automated check rather than render an empty branch.

#### Scenario: Previously-unlogged child records now log
- **WHEN** a shipment, quotation evaluation, profit-and-loss, acceptance report, or goods-receive batch is created or changed
- **THEN** it produces an activity record that the timeline can surface

#### Scenario: A new unlogged child model fails CI
- **WHEN** a request-child model is added to the timeline source without a capture path
- **THEN** the completeness architecture test fails

### Requirement: Upload Attribution

Uploaded documents SHALL record who uploaded them and under which actor type, stamped at attach time; media without such a stamp SHALL be treated as System/Unknown internally and SHALL be denied to non-staff parties by default.

#### Scenario: New upload is attributed
- **WHEN** a file is attached through the shared upload action
- **THEN** the media record carries the uploader id and actor type

#### Scenario: Pre-stamp media is fail-closed for buyers
- **WHEN** a buyer views their timeline and a file predates uploader stamping
- **THEN** that file is not shown to the buyer (deny-by-default)

### Requirement: Buyer Timeline

The buyer portal SHALL present a buyer timeline built from a separate hard-coded additive allow-list resolved from a buyer-authorized request, extending the existing stage stepper, with internal figures, supplier data, staff identities, and internal links structurally excluded.

#### Scenario: Buyer sees safe milestones only
- **WHEN** a buyer views their request timeline
- **THEN** it shows only their own uploads, quotes sent to them, stage milestones, outbound shipment status, invoices, and payments — with stages re-mapped to buyer-facing labels and the causer shown as 'You'/'Your team'

#### Scenario: Leak test — no internal subject reaches the buyer
- **WHEN** the request has supplier quotes/orders/invoices/payments, P&L, quotation evaluations, a buyer order, an inbound shipment, and staff-proof uploads
- **THEN** the buyer timeline contains zero entries whose subject is a supplier record, quotation evaluation, profit-and-loss, buyer order, inbound shipment, or goods-receive record, zero staff-proof uploads, no raw internal stage label (only buyer-facing stage labels), no staff causer name (only 'You'/'Your team'), and no app-panel or sysadmin link
- **AND** the buyer subject-type set is a strict subset of the internal subject-type set

### Requirement: Single Source of Truth

The system SHALL maintain exactly one activity capture system (the live activity log); the dormant `RequestActivity` timeline subsystem SHALL be removed.

#### Scenario: Dead timeline subsystem removed
- **WHEN** the change is applied
- **THEN** the `RequestActivity` model, its enums, policy, factory, relation manager, table migration, and the `Request::activities()` relation no longer exist, and the suite passes


## Context

The ERP has a live audit system (`App\Models\ActivityLog` over Spatie activitylog): header models log via `LogsErpActivity`, rows carry `team_id` + `actor_type` (staff/buyer/supplier/admin/system) + causer, and `EventLogResource` already renders them (actor badge, causer, event badge, subject headline, changed-field count + detail modal) for team admins. A separate in-flight change, `add-line-item-activity-logging`, adds line-level capture and stamps `parent_type`/`parent_id` on item rows.

The user wants an Odoo-chatter-style per-request timeline: staff/admin see everything; buyers see a safe slice (their uploads, quotes sent to them, status/invoice/payment milestones); suppliers later see only their own slice. An adversarial 5-lens review surfaced four things that reshape the naive design, all code-verified:

1. **A dormant `RequestActivity` timeline subsystem already exists with zero writers** — deleting it (not extending it) is a precondition for a single source of truth.
2. **The buyer slice cannot be a filter of the internal feed** — supplier cost/margin, P&L/QE docs, and staff proof live in the same request tree and even the same `attachments` media collection; safety requires an *additive* allow-list.
3. **Five named child models log nothing** (`Shipment`, `QuotationEvaluation`, `ProfitAndLoss`, `AcceptanceReport`, `GoodsReceiveBatch`), so a "complete history" silently omits them.
4. **Uploaded media has no uploader** — "show the buyer's own uploads" is uncomputable until attach-time stamping exists.

Stakeholder constraint: a finance-critical, mistake-averse owner is the primary reader; a single margin leak to a buyer is unacceptable.

## Goals / Non-Goals

**Goals**
- One trustworthy chronological internal timeline per request, reusing the live `activity_log`.
- An audience-scoped visibility helper (single choke point) that yields each viewer party an additive, identity-scoped allow-list — the mechanism behind the staff, buyer, and (later) supplier surfaces.
- Complete capture so the internal history is not silently partial.

**Non-Goals (deferred)**
- The supplier-portal timeline *surface* (the helper supports the `supplier:{id}` party now; the UI ships later).
- Generalizing the timeline to other document roots (only Request ships now).
- A denormalized timeline table, a bespoke multi-source aggregator service, and any new blanket milestone-capture layer.
- Free-text comments (the collaboration write path the user earlier considered; document uploads already cover contribution).

## Decisions

### D1 — Read-time query over the live `activity_log`, reuse `EventLogResource` rendering
The internal source is one `whereIn` over `(subject_type, subject_id)` for the request + its logged child tree, plus one media query, merged and day-grouped. No aggregator service, no denormalized table.
- **Rationale:** `EventLogResource` already renders the exact columns and detail modal over `ActivityLog`, which already stamps team/actor/causer. The gap is per-request scoping, not a new feed model. Once `add-line-item-activity-logging` stamps `parent_type`/`parent_id`, child-tree scope collapses to a `parent` predicate instead of a hand-maintained subject map that drifts.
- **Alternatives rejected:** bespoke `RequestTimeline` aggregator (re-implements rendering); denormalized table (needs its own writers for events `activity_log` already captures); reviving `RequestActivity` (dead, two competing enums).

### D2 — Audience-scoped visibility helper: additive, identity-scoped, single choke point
A `final readonly` helper resolves a **viewer party** to the subjects + entry types it may load and the redaction rules. Parties: `staff`/`admin` (full internal set), `buyer:{companyId}`, `supplier:{companyId}`.
- **Additive, not subtractive:** for non-staff parties the helper returns an allow-list of subjects/entries to *select*; the surface never loads the internal feed and removes items. A positive allow-list cannot leak what it never selects.
- **Identity-scoped:** supplier parties are keyed by company id, so `supplier:#42` can never see `supplier:#43`'s quotes/prices on a shared request. Buyer is single per request but still keyed by company for authorization.
- **Redaction layer (narrow by design):** the primary buyer/supplier protection is *subject selection*, not field redaction. Because `LogsErpActivity` uses `logOnly($this->activityAttributes())`, a buyer-allow-listed subject's `properties` contains only that model's whitelisted attributes — none of which is a supplier cost or margin (e.g. `BuyerQuote` logs `total`/`prepayment_*`, `BuyerInvoice` logs `total`/`amount_paid`, `BuyerPayment` logs `amount`). So there is no supplier-cost/margin figure to strip on the buyer path. Redaction therefore covers only: collapsing the **causer** to a generic label (never a staff person name), re-mapping **stage** values to buyer-facing labels, and **link** allow-listing. It is not a field denylist standing between the buyer and internal figures — subject selection is that boundary.
- **BuyerOrder classification:** `BuyerOrder` is buyer-owned and logged, but its `activityAttributes()` include internal credit mechanics (`credit_released`, `payment_terms_days`). It is therefore **excluded** from the buyer allow-list; the buyer sees `BuyerInvoice` + `BuyerPayment` (the customer-facing money artifacts) instead. Every buyer-owned logged model must be explicitly classified include/exclude — never silently dropped (guarded by an architecture test).
- **Rationale:** this is the user's requested "audience helper," implemented in the only shape the security review found safe. Every surface funnelling through one component means one place to audit.

### D3 — Delete the dormant `RequestActivity` cluster
Remove model, `ActivityType` + `RequestActivityType` enums, policy, factory, `RequestActivitiesRelationManager`, the `request_activities` migration/table, and `Request::activities()`; reconcile `RequestActivityTest`. Grep-verified zero writers.

### D4 — Derive milestones from existing status/timestamps; one explicit write only
Four of the five named milestones already log via `->save()` on audited attributes or the `created` event; only `supplier-quote-sent` writes nothing (`StampSupplierQuoteSent` uses `saveQuietly` on the non-audited `sent_to_supplier_at`). Add exactly one explicit `activity()->performedOn($quote)->log('sent')` there; do not build a milestone-capture layer.

### D5 — Close capture gaps as Phase-1 prerequisites
Stamp `uploader_id` + `actor_type` into media `custom_properties` inside `AttachUploadedFiles::execute()` (resolved from `ActivityLogContext`), removing per-caller duplication; pre-stamp media is treated as System/Unknown and **deny-by-default** for buyers. Add a capture path to the five unlogged child models. Add `goods_receive_batch` morph alias and `Request::supplierInvoices()`.

### D6 — Internal surface = collapsible infolist Section/tab, not a second footer widget
`RequestInformationFlowWidget` is already full-width in the footer; a second full-width widget stacks two panels and conflates "what to do next" with "what has happened." Render history as a paginated section/tab drilling into the shared detail modal, summarizing per save inline ("Buyer quote BQ-123 updated — 4 fields"), never raw inline old→new diffs.

### D7 — Ship first, decoupled from line-item capture (revised)
Originally this change was sequenced after `add-line-item-activity-logging`. Revised after weighing risk and value: the timeline ships **first**, using the interim subject enumeration (guarded by the CI completeness test) as its primary scoping mechanism, and now owns `retention=never`. When line-item capture lands later, scoping swaps to its `parent_type`/`parent_id` predicate and line-level entries appear with no other rework.
- **Rationale:** the timeline's value is visible immediately from data that already exists (uploads, milestones, header changes, the credit ledger), while line-item capture is invisible plumbing bundled with the single riskiest task in either change (the delete-and-recreate persistence refactor). Shipping the surface first also turns it into a validation instrument for that refactor.
- **Accepted limitation (documented, not hidden):** until line-item capture lands, price/quantity edits appear only at header rollup granularity, and a line edit whose header recalc uses `saveQuietly()` may produce no entry. The finance answer to "who changed the price on line 3" arrives with `add-line-item-activity-logging`.

### D8 — Credit ledger is a first-class source; ledger vs change-log kept distinct
`BuyerCreditUsageHistory` already provides an append-only credit ledger: `transaction_type`, `amount`, before/after balances for `available_credit`/`credit_used`/`max_credit_limit`, the causing record (morph to e.g. `BuyerOrder`), `created_by_id`, and approval-gated limit changes via `BuyerCreditLimitRequest(Approval)`. The internal timeline consumes it read-only as a finance lane ("Credit used 5,000 — available 20,000 → 15,000, from BO-123"), linked to the request via the causing order.
- **Principle:** ledger-shaped facts (running balances) belong in the ledger; change-log-shaped facts (field edits) belong in `activity_log`. Neither system re-captures the other's domain.
- **Cleanup (evaluate, don't assume):** `Company::activityAttributes()` currently logs `credit_used`/`credit_limit` as generic field diffs, overlapping the ledger. Verify every credit writer goes through the ledger before trimming those fields; if any direct edit path bypasses the ledger, keep the activity-log coverage as the backstop.

## Risks / Trade-offs

- **Silent-drop of an unregistered child model** (already realized for five models) → back the interim subject enumeration with a CI architecture test that fails when a request-child model using `LogsErpActivity` is missing from the source; migrate to the `parent` predicate once line-item logging lands.
- **Buyer feed regressing into consuming the internal aggregator** → architecture test asserting the buyer subject set is a strict subset excluding all `Supplier*`/QE/P&L types; keep the two code paths in separate services.
- **Row-volume flood** once line-item logging multiplies rows → summarize per save with a changed-field count, day-group, paginate, defer exact values to the detail modal.
- **Deleting `RequestActivity`** → zero writers verified; remove atomically with the suite green.
- **Backfilled media with null uploader** → render as explicit "Unknown/System" internally; deny-by-default for buyers; optional one-time backfill only for unambiguous buyer-origin collections (`buyer_po`).

## Migration Plan

1. Capture prerequisites (D5) + retention=never, delete `RequestActivity` (D3), supplier-quote-sent write (D4).
2. Build the audience helper (D2) + internal source incl. credit-ledger lane (D1, D8) + section UI (D6); completeness + strict-subset architecture tests.
3. Phase 2: buyer surface + redaction + leak test. Phase 3 (later): supplier surface reusing the helper.
4. When `add-line-item-activity-logging` lands: swap interim enumeration for the `parent` predicate; line-level entries appear automatically.
No data migration beyond dropping `request_activities`; new attribution/logging accrues forward. Rollback = revert; the live `activity_log` is unaffected.

## Open Questions

Recommended defaults below; confirm at approval.
- **Internal placement:** collapsible infolist Section (recommended) vs a dedicated "History" tab vs a header slide-over.
- **Historical media backfill:** fail-closed "Unknown/System" and hide pre-stamp files from buyers (recommended) vs a one-time backfill for unambiguous buyer-origin collections.
- **Buyer visibility of rejected/expired quotes & cancelled outbound shipments:** show only active/accepted milestones to avoid confusing the customer (recommended) vs full transparency.
- **Buyer stage feed vs existing stepper:** extend the stepper with document/quote/invoice events rather than a redundant stage feed (recommended).
- **Buyer visibility of their own credit movements:** internal-only in v1 (recommended); a buyer-facing credit lane (their own balances only) is a deliberate later decision.

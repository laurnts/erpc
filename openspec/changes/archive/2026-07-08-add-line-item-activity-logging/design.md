## Context

An ERP activity-logging system already exists (uncommitted on the current branch): a custom `App\Models\ActivityLog` extends Spatie's `Activity` and stamps `team_id` + `actor_type` (staff/buyer/supplier/admin/system) resolved from the active Filament panel guard via `App\Support\ActivityLogContext`; `App\Models\Concerns\LogsErpActivity` wraps Spatie's `LogsActivity` with a per-model `activityAttributes()` allow-list and `logOnlyDirty()` + `dontSubmitEmptyLogs()`; header models log create/update/delete diffs; `EventLogResource` renders the log for team admins only.

The gap is line-level: the eight `*Item` models carry the money, quantity, identity and routing fields that abuse actually manipulates, and none of them log. This design was validated by a five-lens adversarial code review; the decisions below record what that review changed relative to the first-draft design.

Stakeholder constraint: the primary reviewer of this log is finance-critical and mistake-averse. The log must be complete, unambiguous, correctly attributed, and permanent.

## Goals / Non-Goals

**Goals**
- Capture every line-level change to an audited financial/identity/routing field, attributed to the acting user and team, with parent-document context.
- Make the four named abuses visible: price manipulation, quantity change, line deletion, cancellation.
- Reuse the existing Spatie machinery; minimal new infrastructure.
- Permanent, admin-only, append-only-in-practice audit records.

**Non-Goals (deferred to separate changes)**
- DB-level immutability / WORM / tamper-proofing enforcement.
- `batch_uuid` grouping of a save's rows (needs a grouping UI to be useful).
- Detection / alerting / anomaly-scoring on suspicious changes.

## Decisions

### D1 — Reuse Spatie on the item models, do not hand-roll a diff trait
Each `*Item` model uses the existing `LogsErpActivity` (independent subject) and a small `tapActivity(Activity $activity, string $eventName)` hook (a Spatie model method that fires *after* the `logOnlyDirty` diff is computed) to stamp `parent_type`/`parent_id` + a human line label into `properties`.
- **Alternatives considered:** a bespoke `LogsItemActivityToParent` trait with `saved`/`deleted` handlers that redirect the subject to the header and compute diffs by hand. Rejected: it re-implements `wasChanged` gating, `dontSubmitEmptyLogs`, and the standard `attributes`/`old` property shape, breaks the existing detail view's changed-fields counter, and forces a new blade branch — for no reviewer benefit. Filament repeaters and some RFQ paths call `save()` on every row every time; Spatie's dirty gating already suppresses the resulting empty logs.

### D2 — Fix line persistence before trusting item events
Buyer/supplier request and quote saves currently `items()->delete()` (query builder) then recreate lines. Query-builder deletes bypass Eloquent events, so deletions are invisible and recreates emit spurious `created` rows with no diffs. These call sites are converted to in-place reconciliation (match by id: update via `save()`, remove via model `delete()`, create only genuinely new lines).
- **Alternatives considered:** log the diff explicitly at each call site as an interim, deferring the refactor. Rejected by the user in favor of fixing persistence in this change so the trail is correct from day one. Landed first, with regression tests asserting an unchanged final item set.

### D3 — Audit causal inputs + identity/routing levers, not money-only
The allow-list covers the two figures the reviewer reads plus the causal inputs and identity/routing levers, and drops derived intermediates (which are mostly written via `saveQuietly` and never fire an event anyway).
- Sales/purchase item models: `quantity`, `unit_price`, `cost_price`, `tax_rate`, `tax_code_id`, `is_tax_inclusive` (`tax_inclusive` on invoice items), `unit_of_measure_id`, `unit`, `article_id`, `line_total`, `margin_percent`. `SupplierQuoteItem` adds `is_selected` (award flag).
- `RequestItem` (no money columns): `quantity`, `unit_of_measure_id`, `unit`, `article_id`, `supplier_id`, `item_type`.
- Dropped as derived/redundant: `unit_price_exc_tax`, `line_subtotal`, `line_tax`, `tax_amount`, `margin_amount`.
- Headers gain `exchange_rate` + `currency_id` (FX restatement of base-currency receivables/payables).
- **Rationale:** money-only leaves demonstrated silent-fraud holes — `article_id` bait-and-switch, `tax_code_id`/VAT swap, UoM swap, supplier re-award, request supplier reassignment, FX restatement — each moves money while touching zero money fields.

### D4 — Log creations; no create-suppression rule
Line creations are logged unconditionally. The proposed `!parent->wasRecentlyCreated` suppression fails open (the flag is instance-scoped and false on the lazy-loaded parent) and would wrongly hide opening values — a price/margin rigged at creation is itself an abuse to capture.

### D5 — Render FK fields as labels, snapshot on delete
FK audited fields resolve to human labels (`Article [A-100 Pump] -> [B-200 Valve]`) in `properties`. On `deleted`, the full audited set is snapshotted into old-values so the removed magnitude is unambiguous.

### D6 — Retention permanent; no ORM delete-guard
`delete_records_older_than_days` → `null`; `activitylog:clean` must not be scheduled. Real immutability is deferred: the app is already append-only via `ActivityLogPolicy` (deny create/update/delete, no delete UI), and a `booted()` `deleting` guard would be both ineffective (existing purge paths and the test suite delete via query builder, bypassing model events) and test-breaking.

### D7 — Suppress non-team context
Item logging is skipped when `app()->runningInConsole()` or no `team_id` resolves, to avoid orphan `team_id=null` rows (invisible in the team-scoped viewer) from factories, seeders, imports and queued jobs.

## Risks / Trade-offs

- **Editing load-bearing save flows (D2)** → keep final item set identical, land first behind existing `after()`/`afterSave` hooks, regression-test the item set.
- **Observer recalculation** can dirty money fields on a cosmetic-only edit → gate money-row emission on a causal input having changed (D3/task 3.4); weird-path test edits only `notes` and asserts no money row.
- **No header anchor for grouping** (header recalc uses `saveQuietly`) → item rows carry `parent_type`/`parent_id`/label for context; true batched grouping is Phase 2. Accepted by the user.
- **Whole-document delete** hard-deletes items via FK cascade without item events → the header's own `deleted` row covers it; test asserts one header row and zero item rows.

## Migration Plan

1. Land the persistence fix (task group 1) with regression tests — no behavior change to the item set.
2. Add logging traits, morph-map entries, enrichment, retention, header FX (groups 2–4).
3. Viewer touch-ups and the test battery (groups 5–6).
No data migration; new rows accrue going forward. Rollback = revert the change; existing header logs are unaffected.

## Open Questions

- None blocking. Batched header+line grouping and DB-level immutability are explicitly deferred to follow-up changes.

## ADDED Requirements

### Requirement: Actor and Team Attribution

Every activity record SHALL be stamped with the `team_id` that owns it and an `actor_type` (staff / buyer / supplier / admin / system) identifying the kind of account that performed the action, resolved from the Filament panel guard active when the action occurred. The causing user SHALL be recorded as the causer across every panel guard, not only the default web guard.

#### Scenario: Staff action through the app panel
- **WHEN** a signed-in staff user updates an audited record in the app panel
- **THEN** the activity records `actor_type = staff`, `causer_id` = that user, and `team_id` = the active tenant

#### Scenario: Portal action attributed to the portal actor
- **WHEN** a supplier contact changes an audited field through the supplier portal
- **THEN** the activity records `actor_type = supplier` and the supplier user as causer

#### Scenario: Unattributed context falls back to system
- **WHEN** an audited change occurs with no authenticated guard
- **THEN** the activity records `actor_type = system`

### Requirement: Header-Level Change Logging

ERP header documents (buyer/supplier quotes, orders, invoices, payments, requests, companies) SHALL log create, update and delete events for their declared audited attributes, recording the before and after values, and SHALL NOT write a log when no audited attribute changed.

#### Scenario: Audited header field changes
- **WHEN** a quote's `status` changes from `draft` to `cancelled`
- **THEN** an `updated` activity is recorded on the quote with `old.status = draft` and `attributes.status = cancelled`

#### Scenario: Non-audited change is skipped
- **WHEN** only a non-audited header attribute changes on save
- **THEN** no activity is recorded

#### Scenario: FX fields are audited
- **WHEN** a document's `exchange_rate` or `currency_id` changes
- **THEN** the change is recorded, so base-currency restatement is visible

### Requirement: Line-Item Change Logging

Each ERP line-item model (`BuyerQuoteItem`, `BuyerOrderItem`, `BuyerInvoiceItem`, `SupplierQuoteItem`, `SupplierOrderItem`, `SupplierInvoiceItem`, `ShipmentItem`, `RequestItem`) SHALL log create, update and delete of its audited fields as an activity record, attributed to the acting user and team, and enriched with the parent header reference (`parent_type`, `parent_id`) and a human-readable line label.

#### Scenario: Price manipulation on an existing line
- **WHEN** a user lowers an existing quote line's `unit_price` from 100 to 50
- **THEN** an `updated` activity is recorded carrying `old.unit_price = 100`, `attributes.unit_price = 50`, the parent quote reference, and the acting user and team

#### Scenario: Quantity change is captured as a diff
- **WHEN** a user changes an existing line's `quantity` from 10 to 5
- **THEN** the activity records `old.quantity = 10` and `attributes.quantity = 5` (not a delete-and-recreate)

#### Scenario: Line deletion snapshots the removed magnitude
- **WHEN** a user removes a line from an existing document
- **THEN** a `deleted` activity is recorded with the full audited field set (including `quantity`, `unit_price`, `line_total`) captured as old-values

#### Scenario: Opening line values are captured
- **WHEN** a new line is created on a document
- **THEN** a `created` activity records its audited field values

### Requirement: Audited Field Coverage

The audited field set for line items SHALL include causal money/quantity inputs and identity/routing levers, and SHALL exclude purely derived intermediates and cosmetic fields. Foreign-key audited fields SHALL be rendered as human-readable labels (old → new), not raw ids.

#### Scenario: Article bait-and-switch is visible
- **WHEN** a line's `article_id` is swapped to a different article while price and quantity are unchanged
- **THEN** the change is recorded and rendered as `Article [old label] -> [new label]`

#### Scenario: Tax treatment swap is visible
- **WHEN** a line's `tax_code_id` or `is_tax_inclusive` changes
- **THEN** the change is recorded

#### Scenario: Supplier award change is visible
- **WHEN** `is_selected` on a supplier quote line changes (awarding or re-awarding the winning line)
- **THEN** the change is recorded

#### Scenario: Request routing change is visible
- **WHEN** a request line's `supplier_id`, `article_id` or `item_type` changes
- **THEN** the change is recorded

#### Scenario: Cosmetic edit does not record a money change
- **WHEN** only a cosmetic field (e.g. `notes`) changes on a line and a downstream observer recalculates derived money fields
- **THEN** no money-field change row is recorded, because no causal input changed

### Requirement: Reliable Line Persistence for Audit

Buyer/supplier request and quote save flows SHALL persist line items by in-place reconciliation (update changed lines, remove missing lines as models, create genuinely new lines) rather than mass-delete-and-recreate, so that genuine updates and deletions fire the model events the audit trail depends on, while producing an identical final item set.

#### Scenario: Editing preserves the item set
- **WHEN** a request's lines are edited and saved
- **THEN** the resulting set of line ids and values is identical to the equivalent pre-change behavior

#### Scenario: An edit no longer emits spurious creations
- **WHEN** an existing document's lines are edited with one quantity change and one removal
- **THEN** exactly one `updated` and one `deleted` activity are recorded, and no spurious `created` rows for unchanged lines

### Requirement: Context Suppression

Line-item activity SHALL NOT be recorded when running in a console context or when no owning `team_id` can be resolved, so that factory, seeder, import and queued-job writes do not create orphaned records.

#### Scenario: Console creation is not logged
- **WHEN** line items are created from a seeder or console command with no active tenant
- **THEN** no line-item activity is recorded

### Requirement: Retention and Access

Financial activity records SHALL be retained permanently (no scheduled purge) and SHALL be viewable only by team administrators, with the log read-only in the application (no create/update/delete through the UI).

#### Scenario: Records are not auto-purged
- **WHEN** activity records age beyond any prior retention window
- **THEN** they are retained; `activitylog:clean` is not scheduled for financial records

#### Scenario: Non-admin is denied the log
- **WHEN** a non-admin team member opens the event log page
- **THEN** access is forbidden

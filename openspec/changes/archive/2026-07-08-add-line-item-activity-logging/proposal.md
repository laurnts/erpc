# Change: Line-item activity logging for ERP documents

## Why

The ERP activity log is blind below the document header. Unit prices, quantities, margins, discounts, article identity, tax treatment and supplier routing all live on the eight `*Item` line models, and none of them log. Only the header's rolled-up `total` moves, so the deliberately-abusive actions a finance-critical, mistake-averse reviewer needs to see — **price manipulation, quantity changes, line deletion, and bait-and-switch article/tax/supplier swaps** — are invisible.

A naive "listen to item `saved`/`deleted` events" approach was reviewed against the code and found to fail silently: the buyer/supplier quote and request forms persist lines by **mass-deleting and recreating them through the query builder**, which bypasses Eloquent model events. Real deletions and quantity edits would be missed while every edit emitted spurious "line added" rows. This proposal therefore couples line-item logging with the persistence fix that makes it trustworthy, and reuses the existing Spatie machinery rather than hand-rolling a diff trait.

## What Changes

- **Line-item changes become auditable.** Each of the 8 `*Item` models logs create/update/delete of its audited fields as an activity row attributed to the acting user (staff / buyer / supplier / admin) and team, enriched with the parent header (`parent_type`/`parent_id`) and a human line label.
- **The four named abuses become visible at line level:** price manipulation, quantity changes, line deletion (with a full old-value snapshot so removed magnitude is unambiguous), and cancellation (already covered at the header via `status`).
- **Silent fraud vectors are closed** by expanding the audited field set beyond money/quantity to identity/routing levers: `article_id` (bait-and-switch), `tax_code_id` / `is_tax_inclusive` (VAT swap), `unit_of_measure_id` (UoM swap), `is_selected` on supplier quote lines (award / re-award), `RequestItem.supplier_id` and `item_type` (reassignment), and header `exchange_rate` / `currency_id` (FX restatement).
- **BREAKING (internal persistence):** the buyer/supplier request and quote save paths that mass-delete-and-recreate lines are converted to in-place reconciliation (match by id: update changed lines via `save()`, remove via model `delete()`) so genuine deletions and quantity edits fire the events the audit trail depends on. Final item set is unchanged and regression-tested.
- **Reuses existing Spatie machinery** (`LogsErpActivity`, `logOnlyDirty`, `dontSubmitEmptyLogs`, team/actor/causer stamping, morph map, `EventLogResource`, `ActivityLogPolicy`) via a small `tapActivity()` enrichment — no bespoke diff trait, no new property shape, existing detail view and field counter keep working.
- **Financial audit records are retained permanently.** `config/activitylog.php` currently self-deletes after 365 days; retention is set to never and `activitylog:clean` must not be scheduled.
- **Adds the `erp-activity-logging` capability spec**, retroactively documenting the already-shipped header logging plus this line-level behavior.

## Impact

- **New capability spec:** `erp-activity-logging` (none exists today; the shipped header logging is undocumented).
- **Affected code:**
  - Models: `BuyerQuoteItem`, `BuyerOrderItem`, `BuyerInvoiceItem`, `SupplierQuoteItem`, `SupplierOrderItem`, `SupplierInvoiceItem`, `ShipmentItem`, `RequestItem` (add `LogsErpActivity` + `activityAttributes()`).
  - Headers: `BuyerQuote`, `BuyerOrder`, `BuyerInvoice`, `SupplierQuote`, `SupplierOrder`, `SupplierInvoice` (add `exchange_rate`, `currency_id` to `activityAttributes()`).
  - `app/Providers/AppServiceProvider.php` — register the 8 item models in `Relation::enforceMorphMap`.
  - Persistence call sites (BREAKING fix): `EditCustomerRequest.php:59`, `BuyerQuotesRelationManager.php:1690/2105`, `SupplierQuotesRelationManager.php:1510`, `SubmitQuoteCart.php:104`, `ItemsRelationManager.php:781`.
  - `config/activitylog.php` — retention.
  - `EventLogResource` — item subject types in the record-type filter; minimal parent-context line in the detail view.
- **Tests:** `tests/Feature/Erp/ActivityLoggingTest.php` extended (line diffs, persistence regression, context suppression, FK-label rendering).
- **Deferred to later changes:** tamper-proofing / DB-level immutability, `batch_uuid` grouping (needs a grouping UI), detection/alerting on suspicious changes.

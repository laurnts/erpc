# Change: Request activity timeline (audience-scoped)

## Why

Finance-critical, mistake-averse staff need one chronological answer to *"what has been done for this request, and on what timeline"* — including who changed a price or quantity — and buyers (and later suppliers) need a **safe, structurally-isolated slice** of the same history. The raw data mostly already lives in the live `activity_log`, but it is scattered across the request's child tree, five child models capture nothing, uploaded documents record no uploader, and a buyer slice built as a filter of the internal feed sits one missed condition away from leaking supplier cost and margin.

An adversarial review also found a **dormant, abandoned timeline subsystem** (`RequestActivity` + `ActivityType` + `RequestActivityType` enums + relation manager + policy + factory + test + `Request::activities()`) with **zero writers** — a half-built third system that must be removed, not extended, so there is a single source of truth.

This change turns existing audit/collaboration data into trustworthy, audience-scoped surfaces without inventing a parallel capture system.

## What Changes

- **Internal timeline on `ViewRequest`.** A request-scoped, read-time view over the live `activity_log` (reusing `EventLogResource`'s rendering + `event-log-detail` modal), rendered as a collapsible infolist **Section/tab** — not a second full-width footer widget, not a bespoke aggregator service, not a denormalized table.
- **Audience-scoped visibility helper (first-class requirement).** A single choke point resolves, per viewer **party** (`staff`/`admin`, `buyer:{id}`, `supplier:{id}`), the **additive** allow-list of subjects + entry types that party may load, plus the redaction rules. Staff/admin get the full internal set; buyer/supplier parties get a fixed, **identity-scoped** allow-list (so competing suppliers never see each other). Every surface funnels through it; unlisted data is never queried.
- **Close capture gaps** so the history is actually complete: stamp `uploader_id` + `actor_type` on media at attach time; add logging to `Shipment`, `QuotationEvaluation`, `ProfitAndLoss`, `AcceptanceReport`, `GoodsReceiveBatch`; log the column-less `supplier-quote-sent` action; add a `goods_receive_batch` morph alias; add `Request::supplierInvoices()`.
- **Delete the dormant `RequestActivity` cluster** (dead code, zero writers) and reconcile its test.
- **Buyer timeline (Phase 2).** Built from a separate hard-coded additive allow-list on the customer portal, extending the existing `CustomerRequestStagePresenter` stepper, with stage re-mapping, causer redaction, link allow-listing, and a dedicated leak test.
- **Depends on `add-line-item-activity-logging`** (its `parent_type`/`parent_id` stamp collapses child-tree scoping to one query; its retention=never and `*_item` morph aliases are shared preconditions). This change ships **after** it, so the timeline is not launched blind on the price/quantity data it exists to show.

## Impact

- **New capability spec:** `request-activity-timeline`.
- **Depends on:** `add-line-item-activity-logging` (must land first).
- **Affected code:**
  - Capture: `app/Actions/Media/AttachUploadedFiles.php` (uploader stamp); `Shipment`, `QuotationEvaluation`, `ProfitAndLoss`, `AcceptanceReport`, `GoodsReceiveBatch` (logging); `app/Actions/SupplierPortal/StampSupplierQuoteSent.php` (explicit activity write); `AppServiceProvider` morph map (`goods_receive_batch`); `Request::supplierInvoices()`.
  - Surfaces: `app/Filament/Resources/RequestResource/Pages/ViewRequest.php` (internal section); `app/Services/CustomerPortal/CustomerRequestStagePresenter.php` (buyer timeline).
  - New: an audience-visibility component (`app/Services/Timeline/` or `app/Support/`), a timeline entry DTO (`app/Data/`), a day-grouping renderer.
  - Deleted: `RequestActivity` model, `ActivityType` + `RequestActivityType` enums, `RequestActivityPolicy`, `RequestActivityFactory`, `RequestActivitiesRelationManager`, `create_request_activities_table` migration, `Request::activities()`; reconcile `tests/Feature/Erp/RequestActivityTest.php`.
- **Tests:** internal-completeness architecture test (every request-child model has a capture path), audience strict-subset architecture test, and a Phase-2 buyer leak test.
- **Deferred:** the supplier-portal timeline surface (the audience helper is designed for it now, surfaced in a later phase); generalizing the timeline to other document roots.

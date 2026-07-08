## 0. Dependency & cleanup (do first)

- [x] 0.1 Decoupled from `add-line-item-activity-logging` (ships first — see design D7): the interim subject enumeration is the primary scoping mechanism, and this change owns retention. No precondition to confirm; note the accepted header-rollup granularity limitation in the internal UI's empty-state/help copy if practical.
- [x] 0.2 Delete the dormant `RequestActivity` cluster: model, `ActivityType` + `RequestActivityType` enums, policy, factory, RelationManager, `Request::activities()`, and `RequestActivityTest`; historical create migration kept + new drop migration added (repo precedent). Grep-verified zero writers; suite green.

## 1. Capture gaps (Phase 1 prerequisites)

- [x] 1.1 Stamp `uploader_id` + `uploader_actor_type` into media `custom_properties` inside `AttachUploadedFiles::execute()` (resolved from `ActivityLogContext`). Tests: MediaUploaderStampTest (4).
- [x] 1.2 Add `LogsErpActivity` + `activityAttributes()` to `Shipment`, `QuotationEvaluation`, `ProfitAndLoss`, `AcceptanceReport`, `GoodsReceiveBatch`. Tests: ChildModelActivityLoggingTest (9).
- [x] 1.3 Explicit `activity()->performedOn($quote)->event('sent')->log()` in `StampSupplierQuoteSent`. Tests: SupplierQuoteSentActivityTest (3).
- [x] 1.4 Add `goods_receive_batch` to `Relation::enforceMorphMap` (the one unmapped child).
- [x] 1.5 Add `Request::supplierInvoices()` hasMany; payments reached via invoices→payments. Tests: RequestTest.
- [x] 1.6 Architecture/Pest guard: every request-scoped child model in the internal source has a capture path — an unlogged branch fails CI.
- [x] 1.7 `config/activitylog.php` `delete_records_older_than_days` → `null`, documented never to schedule `activitylog:clean`. Tests: ActivityRetentionTest.
- [x] 1.8 Evaluated credit-field overlap. VERDICT: **keep** the `Company` activity-log coverage as a backstop — a staff credit-limit edit path does not route through `BuyerCreditUsageHistory`, so trimming would drop coverage. `Company::activityAttributes()` left unchanged; rationale recorded.

## 2. Audience-scoped visibility helper (first-class)

- [x] 2.1 `TimelineAudience` (`final readonly`) resolves a `TimelineParty` (staff/admin, buyer:{companyId}, supplier:{companyId}) to additive subject rules + entry-type lanes + redaction rules. Supporting: `TimelineParty`, `SubjectRule`, `MediaRule`, `RedactionRules`.
- [x] 2.2 Narrow redaction (causer collapse, stage re-map, link allow-list) — subject selection is the boundary, no field denylist.
- [x] 2.3 Surfaces resolve through the helper (internal source consumes it; buyer/supplier rules present for their phases).
- [x] 2.4 Architecture test: buyer/supplier subject sets are strict subsets of the internal set; supplier party excludes other suppliers' subjects. Tests: TimelineAudienceTest (10).

## 3. Internal timeline surface

- [x] 3.1 `TimelineEntry` DTO (`spatie/laravel-data`) + day-grouping renderer reusing `ActorType` icons/colors.
- [x] 3.2 `RequestTimelineSource` (`final readonly`): one whereIn over (subject_type, subject_id) across request + logged child tree, eager-loaded causer, media lane with uploader.
- [x] 3.2a Credit-ledger lane: `BuyerCreditUsageHistory` merged via the request's buyer orders, amount + before→after balances + causing link. Internal-only v1.
- [x] 3.3 Collapsible `History` Section on `ViewRequest` via `RequestHistoryTimeline` Livewire component + blade: day-grouped, summarized per-save lines, credit lane, pagination, ⓘ granularity note, detail modal reusing `event-log-detail`. No filter bar (v1).
- [x] 3.4 Pest tests: attribution, upload w/ uploader, credit balances, unlogged-branch guard, page smoke. Tests: RequestTimelineTest + RequestResourceViewTest.

## 4. Buyer timeline (Phase 2)

- [x] 4.1a Buyer allow-list is an exhaustive intentional enumeration (helper's `BUYER_EXCLUDED_SUBJECT_TYPES` with per-subject reasons + guard test) — a future logged model cannot be silently omitted.
- [x] 4.1 `PortalTimelineSource` (final readonly): independent hard-scoped additive query from the fixed allow-list, resolved from a `forBuyer`-scoped Request (never `ActivityLogPolicy`/team_id alone). BuyerOrder EXCLUDED (documented in helper).
- [x] 4.2 Buyer redaction: stage re-map via `BuyerRequestStagePresenter::labelForStage` + de-dup; causer → 'You'/'Your team'; drop Supplier/Admin entries; links only to `filament.buyer.resources.requests.*` routes (`RedactionRules`). (Names post customer→buyer rename.)
- [x] 4.3 Activity section on `ViewBuyerRequest` (additive to the stepper), rendered via `filament.buyer.components.request-activity-timeline`.
- [x] 4.4 Pre-stamp/unstamped media deny-by-default (fail-closed) for buyers — only collections with a `MediaRule` are considered; unstamped rows dropped.
- [x] 4.5 Dedicated leak test (`PortalTimelineLeakTest`, 4 tests): zero supplier/internal/inbound subjects, staff-proof + unstamped uploads dropped, presenter-mapped stage labels + generic causer only, buyer subject set ⊂ internal set. Plus BuyerRequestActivityTimelineTest.

## 5. Finalize (Phase 1)

- [x] 5.1 Full suite green (3 pre-existing ArchTest/ResetPassword failures aside) + `vendor/bin/pint --dirty` applied. Phase 1: 36 new tests, 338 assertions.

## 6. Supplier timeline surface (Phase 3)

- [x] 6.1 Supplier-portal timeline on `ViewSupplierRfq` via `PortalTimelineSource::forParty` with the `supplier:{companyId}` party (that supplier's own RFQs/quotes/POs only); mirror leak test in `PortalTimelineSourceTest` asserts Supplier A cannot see Supplier B's quotes.
- [x] 6.2 MOVED to `add-line-item-activity-logging` (its task 30): swapping the interim subject enumeration for the `parent_type`/`parent_id` predicate is that change's landing step, not deliverable here.

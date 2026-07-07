## 0. Dependency & cleanup (do first)

- [ ] 0.1 Decoupled from `add-line-item-activity-logging` (ships first — see design D7): the interim subject enumeration is the primary scoping mechanism, and this change owns retention. No precondition to confirm; note the accepted header-rollup granularity limitation in the internal UI's empty-state/help copy if practical.
- [ ] 0.2 Delete the dormant `RequestActivity` cluster: `RequestActivity` model, `ActivityType` + `RequestActivityType` enums, `RequestActivityPolicy`, `RequestActivityFactory`, `RequestActivitiesRelationManager`, the `create_request_activities_table` migration, `Request::activities()`; reconcile/remove `tests/Feature/Erp/RequestActivityTest.php`. Grep-verify zero writers before deletion; suite green after.

## 1. Capture gaps (Phase 1 prerequisites)

- [ ] 1.1 Stamp `uploader_id` + `actor_type` into media `custom_properties` inside `AttachUploadedFiles::execute()` (resolve from `ActivityLogContext::currentCauser()`/`currentActorType()`); remove per-caller duplication.
- [ ] 1.2 Add a capture path (`LogsErpActivity` + `activityAttributes()`, or an explicit milestone log in the creating Action) to `Shipment`, `QuotationEvaluation`, `ProfitAndLoss`, `AcceptanceReport`, `GoodsReceiveBatch`.
- [ ] 1.3 Add an explicit `activity()->performedOn($quote)->log('sent')` in `StampSupplierQuoteSent` (the column-less action that logs nothing today).
- [ ] 1.4 Add `goods_receive_batch` (and any other unmapped child) to `Relation::enforceMorphMap`.
- [ ] 1.5 Add `Request::supplierInvoices()` hasMany so the enumerator can reach supplier invoices; collect payment subject ids via invoices→payments.
- [ ] 1.6 Architecture/Pest test: every request-scoped child model in the internal source has a capture path — an unlogged branch fails CI instead of rendering empty.
- [ ] 1.7 Set `config/activitylog.php` `delete_records_older_than_days` to `null` and document that `activitylog:clean` must never be scheduled for financial records (ownership moved here from the line-item change; a no-op there if this lands first).
- [ ] 1.8 Evaluate trimming `credit_used`/`credit_limit` from `Company::activityAttributes()` (the credit ledger already records before/after balances): verify every credit writer goes through `BuyerCreditUsageHistory` first; trim only if no direct edit path bypasses the ledger.

## 2. Audience-scoped visibility helper (first-class)

- [ ] 2.1 Build a `final readonly` audience helper that resolves a viewer **party** (`staff`/`admin`, `buyer:{companyId}`, `supplier:{companyId}`) to: the **additive** allow-list of subject types + entry types that party may load, and the redaction rules. Staff/admin → full internal set; buyer/supplier → fixed identity-scoped allow-list.
- [ ] 2.2 Redaction pass (narrow — subject selection is the real boundary, see design D2): collapse causer to a generic label for non-staff parties, re-map stage values to party-facing labels, allow-list links, and drop any entry whose resolved `actor_type` is disallowed for the party. Do NOT rely on a field denylist for supplier cost/margin — those attributes are never present in a buyer-allow-listed subject's `properties` (per-model `logOnly`); the leak test must prove the *subject* is absent, not a field name.
- [ ] 2.3 Route every timeline surface (internal, buyer, later supplier) through this helper — no surface queries `activity_log`/media directly.
- [ ] 2.4 Architecture test: the `buyer` and `supplier` subject sets are strict subsets of, and structurally distinct from, the internal set; a supplier party's set excludes every other supplier's subjects.

## 3. Internal timeline surface

- [ ] 3.1 Timeline entry DTO (`spatie/laravel-data`) + a day-grouping renderer reusing `ActorType` `HasIcon`/`HasColor`.
- [ ] 3.2 Internal read source: one `whereIn` over `(subject_type, subject_id)` across the request + logged child tree (via the helper's staff allow-list, interim enumeration), eager-load causer, merge request+child media (with uploader), derive milestones from logged status + timestamp columns.
- [ ] 3.2a Credit-ledger lane: merge `BuyerCreditUsageHistory` rows reachable via the request's buyer orders (and limit-change approvals for the request's buyer) into the internal feed, rendering amount + before→after balances + causing record link. Read-only; internal surface only in v1.
- [ ] 3.3 Render as a collapsible infolist Section (or tab) on `ViewRequest`, paginated, summarizing per save inline ("Buyer quote BQ-123 updated — 4 fields"), drilling into the shared `event-log-detail` modal. Never raw inline old→new diffs.
- [ ] 3.4 Pest tests: price/quantity edit appears attributed to actor+time; upload appears with uploader; unlogged-branch guard; pagination/day-grouping smoke.

## 4. Buyer timeline (Phase 2)

- [ ] 4.1 Build the buyer source as an independent hard-scoped additive query from the fixed allow-list — Request(self), BuyerQuote(status != draft), Shipment(type=OUTBOUND), BuyerInvoice, BuyerPayment, buyer-uploaded `attachments` (custom_properties.actor_type === buyer), BuyerQuote `buyer_po` — resolved from a `scopeForBuyer`-authorized Request (never `ActivityLogPolicy`, never team_id alone). Explicitly classify every buyer-owned logged model include/exclude: `BuyerOrder` is EXCLUDED (carries internal credit mechanics `credit_released`/`payment_terms_days`); document the reason inline so it is not a silent drop.
- [ ] 4.1a Architecture test: the buyer allow-list is an exhaustive, intentional enumeration — every model using `LogsErpActivity` is either in the buyer set or explicitly excluded-with-reason, so a future buyer-owned logged model cannot be silently omitted (mirrors the internal completeness guard 1.6).
- [ ] 4.2 Buyer redaction: re-map stage via `CustomerRequestStagePresenter::labelForStage()` + de-duplicate; collapse causer to 'You'/'Your team'; drop any entry resolving to `actor_type` Supplier/Admin; buyer links resolve only to `CustomerRequestResource` routes (default null).
- [ ] 4.3 Extend the existing `CustomerRequestStagePresenter` timeline component on `ViewCustomerRequest` (additive to the shipped stepper), not a second competing widget.
- [ ] 4.4 Pre-stamp/unstamped media is deny-by-default (fail-closed) for buyers.
- [ ] 4.5 Dedicated leak test (assert the SUBJECT is absent, not a field name — a field-name assertion passes trivially because those attributes are never logged): seed supplier quotes/orders/invoices/payments, P&L, QE, inbound shipment, goods_receive, a `BuyerOrder`, and staff-proof uploads in `attachments`. Assert the buyer timeline contains ZERO entries whose `subject_type` is any of `supplier_quote`/`supplier_order`/`supplier_invoice`/`supplier_payment`/`quotation_evaluation`/`profit_and_loss`/`buyer_order` or an inbound/goods-receive record; ZERO staff-proof uploads (non-buyer `actor_type` media); every stage entry uses the buyer-facing `CustomerRequestStagePresenter` label (never the raw `stage` enum value); every causer renders as 'You'/'Your team' (assert no seeded staff user name appears); and no entry link contains an app-panel or sysadmin path segment. Additionally assert the buyer subject-type set is a strict subset of the internal set.

## 5. Finalize

- [ ] 5.1 Run affected tests + `vendor/bin/pint --dirty`; then offer the full suite.

## 6. Supplier timeline surface (Phase 3 — later, out of this change's shipping scope)

- [ ] 6.1 Reuse the audience helper's `supplier:{companyId}` party to build the supplier-portal timeline (that supplier's own RFQs/quotes/POs only); mirror the buyer leak test asserting Supplier A cannot see Supplier B.
- [ ] 6.2 When `add-line-item-activity-logging` lands: swap the interim subject enumeration for its `parent_type`/`parent_id` predicate; line-level price/quantity entries then appear with no further timeline changes.

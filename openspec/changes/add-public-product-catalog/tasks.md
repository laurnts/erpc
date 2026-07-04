# Tasks: add-public-product-catalog

**Prerequisite: `refactor-customer-portal-for-portal-symmetry` (Slice 0) is implemented and archived first.** Slices below are independently deployable; Slices 5–6 (catalog track) can run in parallel with Slices 2–4 (supplier track) any time after Slice 1. Kill-switch flags: `supplier_portal_enabled`, catalog route flag.

## Slice 1 — Data model + admin plumbing (additive, no UI risk)

- [x] 1.1 Migration `supplier_articles`: `supplier_price` (decimal 15,4), `supplier_price_currency_id` (FK nullOnDelete), `supplier_price_updated_at`, `available_quantity` (decimal 15,4), `quantity_updated_at`; index `(article_id, is_active)`; dedupe multi-preferred rows (keep lowest id) then partial unique `UNIQUE (article_id) WHERE is_preferred`
- [x] 1.2 `SupplierArticle` pivot model (extends `Pivot`; touch logic for `*_updated_at`); add new columns to `withPivot` on `Article::suppliers()` / `Company::suppliedArticles()`; factory
- [x] 1.3 `App\Actions\SupplierArticles\SetPreferredSupplier` (transactional demote-siblings/promote); wire into both admin relation managers' create/attach/edit paths
- [x] 1.4 `App\Actions\SupplierPortal\UpdateSupplierArticleOffer` (whitelisted 4 fields + timestamp stamps); admin relation managers gain the new fields through it (staff can maintain supplier prices/quantities before any portal exists)
- [x] 1.5 `GenerateSupplierQuotesForRequest::getLastQuotedPrice()` → prefer `supplier_price` (currency respected), fallback `last_quoted_price`; stop cross-currency verbatim copies
- [x] 1.6 Pest tests: pivot model, preferred-supplier uniqueness (incl. race via unique index), offer update stamps, prefill source + currency behavior

## Slice 2 — Supplier panel shell + article self-service

- [x] 2.1 `config/auth.php` `supplier` guard (same `users` provider); `config/app.php` supplier path/domain/cookie/enabled keys; `PanelDomain::supplierHost()`; `SupplierPanelProvider` (id `supplier`, `strictAuthorization()`, `PortalPanelConfigurator::apply()`); register in `bootstrap/providers.php`; `EnsureSupplierPortalEnabled` middleware
- [x] 2.2 `User::hasActiveSupplierPortalAccess()` (+ `activeSupplierPortalCompanyIds()`), `canAccessPanel('supplier')` arm; `SupplierPortalContext` (from the shared core)
- [x] 2.3 Invitation flow: `InviteSupplierPortalUser` + `SupplierPortalUserInvitationMail` + supplier-panel `AcceptPortalInvitation` (creates `portal = supplier` membership, marks email verified); invite action on supplier company records
- [x] 2.4 `SupplierLogin`, `SupplierDashboard` + `SupplierStalePricesWidget`, `SupplierOpenRfqsWidget` (RFQ widget ships empty-gated until Slice 3)
- [x] 2.5 `SupplierArticleResource` (slug `my-articles`): list + edit of own rows via `SupplierArticle::scopeForSupplier()`; article identity/unit read-only; only the 4 supplier-writable fields in `Schemas/SupplierArticleForm.php`; **no create/delete/attach anywhere in the panel**; `SupplierArticlePolicy` (view/update own-company rows; create/delete denied)
- [x] 2.6 Pest tests (`tests/Feature/SupplierPortal/`): access matrix (internal user, buyer-only membership, supplier membership, dual-role person-level both directions), invitation accept, own-rows-only scoping, field whitelist against tampered payloads, deactivation force-logout

## Slice 3 — RFQ participation (receive, quote, decline)

- [x] 3.1 Migration `supplier_quotes`: `submitted_via` enum default `internal`, `submitted_at`, `submitted_by_user_id` (nullOnDelete), `declined_at`, `sent_to_supplier_at`; backfill `sent_to_supplier_at` from `notification_metadata` where a send is recorded
- [x] 3.2 "Send to Suppliers" path stamps `sent_to_supplier_at`; re-send to a declined supplier clears `declined_at`/`submitted_*` and re-stamps; `QuoteToSupplierMail` gains a portal deep-link
- [x] 3.3 Decline rules: `CheckAwaitingSupplierQuotesJob` adds `whereNull('declined_at')`; expiry sweep skips declined rows
- [x] 3.4 `SupplierRfqResource` (slug `rfqs`): List tabs Open/Submitted (Won/Lost tabs land in Slice 4); View with item hierarchy (own `is_selected` only); `SupplierQuote::scopeForSupplierPortal()` (own company AND `whereNotNull('sent_to_supplier_at')`); `SupplierQuotePolicy` supplier branch via `ResolvesPanelContext`
- [x] 3.5 `SubmitSupplierRfqResponse` (same write as admin "Input price": per-item prices, validity, notes, quotation upload via `AttachUploadedFiles`; `exchange_rate` server-resolved, client values rejected; stamps `submitted_*`; observer PENDING→RECEIVED unchanged) + `DeclineSupplierRfq` (stamps `declined_at`, notifies team) + `SupplierQuoteSubmittedNotification`
- [x] 3.6 `SupplierRfqStatusPresenter` (precedence: Declined → Awaiting your quote / Expired → Submitted — under review); admin views show a Declined badge from the timestamp
- [x] 3.7 Pest tests: unsent RFQs invisible, submit happy/tampered-rate/expired-validity, decline + reminder-job + expiry interactions, re-send reset, confidentiality (no buyer identity, no other suppliers, no comparison/QE data in any portal response)

## Slice 4 — Won/lost outcomes via announce

- [x] 4.1 `AnnounceRfqOutcomes` action: `markAsRejected()` on sibling RECEIVED quotes with zero selected items; fires `SupplierQuoteOutcomeNotification` (won/lost) once; locks further `applySelections()`; offered/gated at QE approval and prompted at PO issuance
- [x] 4.2 Widen `SupplierQuoteComparison::quotes()` and `QuotationEvaluation::syncSnapshotData()` to include REJECTED for display; `SupplierQuoteObserver` guard: outcome-only transitions skip QE re-sync/approval reset; `obtained` shortcut fires no notification
- [x] 4.3 Portal Won/Lost tabs + `SupplierRfqOutcomesWidget`; pre-announcement SELECTED renders "Submitted — under review"
- [x] 4.4 Pest tests: no notification before announce despite SELECTED churn, single notification on announce, REJECTED rows still visible in matrix/QE snapshot, approved QE not reset by announcement, round lock

## Slice 5 — Catalog price plumbing (admin-only; parallel after Slice 1)

- [x] 5.1 Migration `articles`: `list_price` (decimal 15,4), `list_price_updated_at`, `show_in_product_grid` (default false, indexed with team_id), `price_review_needed` (default false, indexed)
- [x] 5.2 `Article`: `InteractsWithMedia` + `product_images` collection (ordered, thumb/medium conversions); casts; factory states — media + `withProductImages` factory state done; `list_price` casts land with 5.1
- [x] 5.3 ArticleResource: Images section; Public Catalog section (flag + `list_price`; saving stamps `list_price_updated_at`, clears review flag); review badge + table filter on the persisted flag — Images section done (via `filament/spatie-laravel-media-library-plugin`, excluded from inline modals); Public Catalog section done (non-modal form only; grid toggle column + list price column + ternary filters)
- [x] 5.4 Suggest-price action (cost rungs preferred `supplier_price` → preferred `last_quoted_price` → lowest converted active `supplier_price`; per-rung FX-failure notices; `MarginConvention` + `TeamErpSettings::default_margin_percent`)
- [x] 5.5 `price_review_needed` recompute hooks (`UpdateSupplierArticleOffer`, `SetPreferredSupplier`, list-price save) + daily `articles:refresh-price-review` command
- [x] 5.6 Pest tests: suggest rungs incl. FX skip/abort notices, review-flag lifecycle, media collection — media collection tests done (collection, conversions, ordering, mime rejection, factory state, create-form upload); price tests done (`tests/Feature/Erp/CatalogPricingTest.php`)

## Slice 6 — Public catalog + cart + buyer registration

- [ ] 6.1 `config/catalog.php` team resolution; catalog query scope (team + `is_active` + `show_in_product_grid`)
- [ ] 6.2 Replace `/`: Livewire catalog page — tag category menu (only tags on grid-visible articles), debounced search (name/SKU/description), paginated grid (primary image, tags, `list_price` or "Price on request", stock badge via `withExists` incl. "On request" state, quantity + add)
- [ ] 6.3 Product detail `/products/{article}` with gallery; 404 unless grid-visible; header links customer/supplier/staff logins
- [ ] 6.4 Session cart service + Livewire components (add, summary, header badge); guest submit gate preserving cart through login redirect
- [ ] 6.5 `RequestSubmissionMethod::Catalog`; submit action → `Request` + `RequestItems` per spec (line validation, confirmation with request number, cart cleared)
- [ ] 6.6 `portal_registration_requests` migration + model; public registration form (linked from header + submit gate); duplicate email/application rules
- [ ] 6.7 Approval Filament resource (approve → buyer Company + User + active `portal = customer` membership + verification/welcome mail; reject → mail); application-received mail
- [ ] 6.8 Pest tests: grid scoping/search/category/detail-404, price + stock display states, no supplier/cost leakage in public responses, cart lifecycle, guest gate, submission records, registration lifecycle (nothing exists pre-approval; approved user signs in and submits)

## Hardening (every slice)

- [ ] H.1 `vendor/bin/pint --dirty`; `composer test:types`; architecture tests; full `php artisan test --compact` green; ≥80% coverage on new code
- [ ] H.2 `openspec validate add-public-product-catalog --strict`

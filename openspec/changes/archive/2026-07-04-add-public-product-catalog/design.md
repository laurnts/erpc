# Design: Public product catalog + supplier portal

Decisions D1–D10. Full evidence, alternatives, and the verification critique: `architecture.md` in this change directory.

## Context

Multi-tenant B2B trading ERP, single active tenant (central purchasing team). `TeamScope` is not applied globally to `Article` and fatals for guests, so the public layer scopes explicitly. Reused as-is: Spatie Media Library, Tags-as-categories, `MarginConvention` (sell-based: `sellNet = cost / (1 − m/100)`), `ExchangeRate`/`CurrencyService`, `TeamErpSettings::$default_margin_percent`, the customer portal pattern (post-refactor), `SupplierQuote` machinery, portal-originated Requests.

## Goals / Non-Goals

**Goals**: public browsing with deliberately published prices; quote cart → standard `Request`; approval-gated buyer registration; supplier self-service for price/stock; supplier RFQ participation with announced outcomes.

**Non-Goals**: no payments/checkout; no inventory ledger (`available_quantity` is a supplier-maintained signal); no multi-storefront; no supplier self-registration (invite-only); **no supplier-created articles or self-assignment** — listings are staff-owned; supplier-initiated listing requests (with central purchasing approval) are a named future capability; no supplier editing of payment terms/tax in portal v1.

## Decisions

### D1. Public tenancy: explicit config-resolved team
Catalog queries filter by `team_id` from `config('catalog.team_id')`, fallback first team; never `Filament::getTenant()` or `auth()`.

### D2. Price: human-published `list_price`, fed by supplier prices, guarded by a persisted review flag
Public pages read exactly one indexed column: `articles.list_price` (decimal 15,4, team default currency); null ⇒ "Price on request". Live derivation is rejected: a supplier who maintains prices and browses the site back-computes the exact margin (`public/mine = 1/(1−m)`), and supplier edits would silently reprice the storefront.
- **Suggest price** (admin action): cost = preferred supplier's `supplier_price` → fallback preferred's `last_quoted_price` → fallback lowest *converted* `supplier_price` among active links → none ⇒ no suggestion. Currency via `CurrencyService::convert()` to team default; missing rate on rungs 1–2 aborts with a named-currency notice; on rung 3 unconvertible candidates are skipped **with notice**, abort only if none convert. Margin = `MarginConvention::netUnitPrice(cost, TeamErpSettings::default_margin_percent)`. Suggestion only fills the input; **saving is the publish act** (stamps `list_price_updated_at`, clears `price_review_needed`).
- **Staleness**: `articles.price_review_needed` (persisted boolean — a live cross-currency minimum is not SQL-expressible for a table filter) recomputed on: supplier price writes, `SetPreferredSupplier`, `list_price` save, and a daily `articles:refresh-price-review` command (FX drift). Flag rule: `list_price` set AND (margin vs best converted cost < default margin OR best cost unconvertible). Supplier edits surface for review; they never auto-reprice.

### D3. Price & stock live on `supplier_articles`; new pivot model
Supplier-owned columns: `supplier_price` (15,4), `supplier_price_currency_id`, `supplier_price_updated_at`, `available_quantity` (15,4, null = unknown), `quantity_updated_at`. Staff-owned stay staff-only: `is_preferred`, `is_active`, `supplier_sku`, `notes`, `last_quoted_*` (backward-looking history — deliberately separate from the forward-looking standing offer). New `SupplierArticle` pivot model (Filament resource + policy target under `strictAuthorization()`, home of the touch logic). Postgres partial unique index `UNIQUE (article_id) WHERE is_preferred` + shared transactional `SetPreferredSupplier` action wired into all writers (migration first dedupes deterministically). Stock badge: "In stock" ⇔ EXISTS active link with `available_quantity > 0` AND `companies.is_supplier`; all-null quantities ⇒ "On request" (third state, so the catalog doesn't launch all-red); via `withExists`/subquery, never per-card accessors.

### D4. Buyer registration: pending-application table
`portal_registration_requests` (name, email, company name, phone, message, hashed password, status, decided_by/at). No `User`/`Company`/membership rows before approval. Approval creates buyer Company + User (stored hash) + active `portal = customer` membership, with an email-verification round-trip (application email was never verified); rejection is a status flip + mail.

### D5. Supplier portal: third Filament panel `/supplier`, structural mirror of the post-refactor customer portal
Guard `supplier` (same `users` provider), `PortalPanelConfigurator`, portal-typed membership (`portal = supplier` AND `companies.is_supplier` — requires the refactor change's CP-2). Directory mirror: `app/Filament/Supplier/{Pages,Resources,Widgets}`, `Actions/SupplierPortal/`, `Services/Portal/SupplierPortalContext`, sibling test dir. Two resources: `SupplierArticleResource` ("My Articles": article identity read-only, exactly 4 supplier-writable fields — price, currency, quantity, lead time; create/delete/attach denied) and `SupplierRfqResource` (tabs Open/Submitted/Won/Lost). Minimal dashboard (2 widgets). Invitations reuse `PortalInvitation` with `portal = supplier`.

### D6. Cart: server-side session, Livewire
`article_id → quantity` in the session; survives navigation and the login redirect. No cross-session persistence in this change.

### D7. Submission: reuse portal-originated Request machinery
Cart submit (active customer-portal user) → `Request` (`buyer_id` = active portal company, `stage = draft`, `submission_method = catalog` — new enum case, `submitted_by_user_id`, `submitted_at`) + one `RequestItem` per line. Downstream, staff sourcing can generate supplier quotes as today — supplier-portal visibility still requires the explicit send (D10).

### D8. Homepage replacement
`/` serves the catalog (tag category menu, search, grid); detail at `/products/{article}` (404 unless grid-visible). Header links the three logins (customer / supplier / staff).

### D9. Customer portal refactor lands first
Separate prior change `refactor-customer-portal-for-portal-symmetry` (renames, portal-typed memberships/security fix, shared plumbing, `Schemas/` convention). All supplier-portal work builds on it.

### D10. RFQ participation: the pending `SupplierQuote` IS the RFQ; outcomes are announced, not leaked
No new tables. `supplier_quotes` gains `sent_to_supplier_at` (portal visibility gate — stamped by the staff "Send to Suppliers" mail dispatch; auto-generated quotes stay invisible until actually sent), `submitted_via/at/by`, `declined_at`.
- **Decline** keeps `status = PENDING` with three explicit rules: reminder job adds `whereNull('declined_at')`; expiry sweep skips declined rows (presenter precedence: Declined over Expired); staff re-send clears `declined_at`/`submitted_*` and re-stamps the gate.
- **Submission** performs the same write as the admin "Input price" action (per-item prices, validity, notes, document upload via `AttachUploadedFiles`); `exchange_rate` is **always server-resolved** from `ExchangeRate` — client values rejected (it drives comparison ranking); observer PENDING→RECEIVED fires unchanged; team notified.
- **Outcomes**: `applySelections()` keeps its reversible semantics. A terminal staff action `AnnounceRfqOutcomes` (offered at QE approval / PO issuance) marks zero-selection losers REJECTED, fires the single won/lost notification, and locks the round. Comparison matrix and QE snapshot queries widen to include REJECTED for display; the quote observer gains a guard so outcome-only transitions don't reset an approved QE. Pre-announcement, the portal shows "Submitted — under review" regardless of internal churn.

## Risks / Trade-offs

- Stale quantities/prices → `*_updated_at` visibility + stale-price widget make updates cheap; review flag guards the public price.
- Public exposure is whitelist-only (name, description, images, tags, unit, `list_price`, derived badge, jsonb attributes); supplier identity/costs/codes never render. Spec scenarios + tests assert each "never".
- Announce-outcomes changes admin-visible status semantics post-announcement (losers become REJECTED, matrix keeps showing them) — communicated in its own slice.
- Single-team assumption isolated behind one config point.

## Migration

Additive schema only; `show_in_product_grid` defaults false (storefront launches empty until articles are published). `sent_to_supplier_at` backfilled from `notification_metadata` where a prior send is recorded. Preferred-supplier dedup runs before the partial unique index.

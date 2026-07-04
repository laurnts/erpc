# Change: Public product catalog, per-supplier price/stock, quote cart, buyer self-registration, and supplier portal with RFQ participation

## Why

The homepage at `/` is a static marketing page; every data-facing screen sits behind the internal panel or the invite-only customer portal. The business wants a Magento-style public storefront: visitors browse the article catalog (category menu, large search bar, product grid), see deliberate prices and availability, pick quantities, and convert into a quote request in the existing Request workflow. Prices and stock are supplier-level facts — each supplier maintains their own standing price and available quantity for the articles they are assigned to — and suppliers must also participate in RFQs from their own portal: receive solicitations, quote per item, and track whether their quote was picked. None of this exists today: `Article` has no images, no published price, no stock signal, no public flag; there is no supplier-facing surface at all; and new buyers cannot register.

Full architecture rationale, investigation evidence, and the adversarial critique behind the decisions here: `architecture.md` in this change.

## What Changes

- **Per-supplier price & stock** (`supplier_articles` pivot + new `SupplierArticle` pivot model): supplier-owned `supplier_price` (+ currency, updated-at) separate from the staff-owned `last_quoted_price` history; `available_quantity` (+ updated-at); DB-enforced at-most-one preferred supplier per article via partial unique index and a shared `SetPreferredSupplier` action
- **Article catalog fields**: `product_images` media collection, `show_in_product_grid` flag, human-published `list_price` (decimal 15,4, team default currency) + `list_price_updated_at` + persisted `price_review_needed` staleness flag
- **Assisted pricing**: admin "Suggest price" action — preferred supplier's `supplier_price` (fallbacks defined) converted via ExchangeRate, margined with the existing `TeamErpSettings::$default_margin_percent` through `MarginConvention`; saving is the publish act; supplier price edits flag articles for review but never move a public number
- **Public catalog** (replaces the marketing homepage): tag-based category menu, debounced search, grid + product detail with gallery; price shown from `list_price` only (null ⇒ "Price on request"); stock badge derived from active supplier quantities ("In stock" / "Out of stock" / "On request" when unknown)
- **Quote cart**: session-backed; signed-in portal users submit it as a `Request` (`submission_method = catalog`) + `RequestItems`; guests are asked to sign in or register at submit time
- **Buyer self-registration with approval (must-have)**: pending-application table; staff approve (creating buyer Company + User + portal access, with email verification) or reject; no records exist before approval
- **Supplier portal** (new Filament panel `/supplier`, invite-only, mirrors the post-refactor customer portal structure): read-only listing of assigned articles with self-service editing of own price/quantity/lead-time only — **no article creation or self-assignment** (new listings require central purchasing approval; supplier-initiated listing requests are a named future capability)
- **Supplier RFQ participation** (no new tables — the pending `SupplierQuote` is the RFQ): visibility gated on `sent_to_supplier_at` (stamped by the staff "Send to Suppliers" action); per-item quote submission with server-resolved exchange rate; decline with fully specified semantics; outcomes (won/lost) revealed only via an explicit staff "Announce outcomes" terminal action — never from raw status transitions

## Impact

- **Depends on**: `refactor-customer-portal-for-portal-symmetry` (must land first — portal-typed memberships, shared portal plumbing, canonical structure)
- Affected specs:
  - `public-catalog` — new capability
  - `supplier-portal` — new capability (article self-service + RFQ participation + confidentiality)
  - `erp-trading-core` — MODIFIED Articles Entity, Article-Supplier Relationship; ADDED Catalog List Pricing
  - `erp-quoting` — MODIFIED Generate Supplier Quotes from Item Assignments (prefill from supplier price, currency-safe); ADDED RFQ send gate, portal submission, decline, outcome announcement
  - `customer-portal` — ADDED Buyer Self-Registration with Approval; ADDED Catalog Quote Cart Submission
- Affected code (high level): migrations (`supplier_articles`, `articles`, `supplier_quotes`, `portal_registration_requests`, `auth`/`app` config); `SupplierArticle` pivot model; `SupplierPanelProvider` + `app/Filament/Supplier/**` mirroring the customer portal; actions in `Actions/SupplierPortal/` and `Actions/SupplierArticles/`; public Livewire catalog components replacing `HomeController` views; `RequestSubmissionMethod::Catalog`; announce-outcomes wiring in comparison/QE/PO flow
- Delivery: 6 deployable slices after the refactor change (see tasks.md); catalog track can run in parallel with supplier-portal track from slice 1
- Single-tenant assumption: the public catalog serves one team (config-resolved, defaults to first team)

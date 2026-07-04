# Final Architecture Plan — Supplier Portal, Per-Supplier Pricing, Public Catalog (rev. 2)

Synthesized from three designs and four investigation reports; revised against the verification critique. Major revisions: outcome finalization is now an explicit terminal event (findings 1+3), portal RFQ visibility is gated on actual send (finding 2), decline semantics are fully specified (finding 4), portal membership carries a `portal` type (finding 5, reversing the original company-flags-only decision), `exchange_rate` is server-resolved (finding 6), and the needs-review filter is backed by a persisted flag (finding 7).

---

## A. Customer portal refactor decision

**Verdict (unanimous): targeted refactor first, no restructure.** The skeleton — panel + dedicated guard + session-cookie isolation + `company_portal_users` membership pivot + context service + panel-branched policies + `strictAuthorization()` + presenter + observer notifications — is sound and becomes the canonical portal pattern. Explicitly rejected: moving portals into `app-modules/` (module-isolation rules forbid the dependencies both portals need: MarginConvention, currency services, Request flow), splitting panel-branched policies into per-panel classes, and any panel factory/abstraction beyond one static configurator.

Ship as its own OpenSpec change (`refactor-customer-portal-for-portal-symmetry`), landed **before** any supplier-portal code. ~1.5–2.5 days (CP-2 grew a membership column), covered by `tests/Feature/CustomerPortal/CustomerPortalTest.php` (654 lines). No behavior change except CP-2, which is a security fix.

| # | Item |
|---|------|
| CP-1 | **Rename only the colliding symbols**: `PortalContext` → `CustomerPortalContext`, `InitializePortalContext` → `InitializeCustomerPortalContext`, `User::hasActivePortalAccess()` split (CP-2), `activePortalCompanyIds()` → `activeCustomerPortalCompanyIds()` (+ supplier twin later). **Keep** table/model names (`company_portal_users`, `CompanyPortalUser`, `PortalInvitation`) and existing customer-side `Portal*` widget/notification names — the supplier portal mirrors the *structure*, not the Portal-prefixed legacy names (see §D). `portal_invitations` gains a `portal` enum column (`customer`/`supplier`), **default `'customer'` with backfill of existing rows** so in-flight unaccepted invitations keep working — one table, one accept flow. |
| CP-2 | **Security fix — person-level portal membership, not company flags alone.** *(Revised per critique finding 5; original company-flags-only decision withdrawn.)* `company_portal_users` gains a `portal` enum column (`customer`/`supplier`), default `'customer'` with backfill; unique index changes from `(company_id, user_id)` to `(company_id, user_id, portal)` so one person at a dual-role company can hold both memberships — but only when explicitly invited to each. On invitation accept, `portal_invitations.portal` flows into the membership row (the enum is load-bearing, not decorative). Access checks become two-condition: `hasActiveBuyerPortalAccess()` = active membership with `portal = customer` **AND** `companies.is_buyer`; `hasActiveSupplierPortalAccess()` = `portal = supplier` AND `is_supplier`. This fixes the confirmed leak (a supplier-only membership currently passes `canAccessPanel('customer')`, `User.php:151-156`) *and* prevents the reverse escalation: a buyer contact of a dual-role company never silently gains supplier-portal write access to prices/RFQs. Filter `activeMemberships()` (company switcher) per-panel the same way. Feature tests assert both denial directions at a dual-role company. |
| CP-3 | **Context service**: two thin `final readonly` classes in `app/Services/Portal/` — `CustomerPortalContext`, later `SupplierPortalContext` — sharing a private parameterized core *(guard string, membership portal type + company-type flag, session-key prefix)*. Kills the hardcoded `auth()->guard('customer')` and the `?? auth()->user()` default-guard fallback (latent cross-panel leak). Two classes, not one contextually-bound class: Filament's static-heavy code resolves by class name (`app(X::class)`), so distinct classes beat conditional container bindings. |
| CP-4 | **Scopes as single source of truth**: `Request::scopeForBuyer()`, `Shipment::scopeForBuyerCompany()` replace the 4 hand-written `buyer_id` where-clauses (resource + 3 widgets). Establishes the scope-per-column convention the supplier portal repeats. |
| CP-5 | **Policy concern**: extract `App\Policies\Concerns\ResolvesPanelContext` — `isCustomerPanel()`/`isSupplierPanel()` discriminated by **auth guard** (fallback: panel id) + membership-ownership helpers. Replaces the 3 private copies in `RequestPolicy`/`BuyerQuotePolicy`/`ShipmentPolicy`. |
| CP-6 | **Shared plumbing, no inheritance**: (a) collapse the byte-identical `AuthenticateAppPanel`/`AuthenticateCustomerPanel` into `AuthenticatePanelUser` (keeps force-logout-on-lost-access); (b) generalize `UseCustomerPanelSession` → `UsePanelSession` driven by a config map path-prefix → cookie (Livewire-snapshot sniffing exists exactly once); (c) extract the duplicated FileUpload→media loop into `App\Actions\Media\AttachUploadedFiles`; (d) `App\Support\PortalPanelConfigurator::apply(Panel): Panel` (~40 lines of shared middleware/theme/auth defaults). Stop there. |
| CP-7 | **Form off the Create page**: `CreateCustomerRequest::formComponents()` → `CustomerRequestResource/Schemas/CustomerRequestForm.php`. Sets the `Schemas/` convention both portals use. |

Deferred (non-blocking, from the audit): document-edit test gap, `submission_method_choice` mount fill, widget `canView` duplication, renaming customer `Portal*` widgets to `Customer*` (cosmetic; do opportunistically, never as a blocking task).

---

## B. Data model

### B1. `supplier_articles` — one migration + pivot model (unanimous)

| Column | Type | Ownership |
|---|---|---|
| `supplier_price` | decimal(15,4) nullable | **Supplier** — forward-looking standing offer. **Separate from** `last_quoted_price` (staff-owned backward-looking RFQ history, stuck at 15,2). Merging rejected: different semantics, different write-owner, wrong precision. |
| `supplier_price_currency_id` | FK `currencies`, nullOnDelete | Supplier; portal defaults to `companies.default_currency_id` |
| `supplier_price_updated_at` | datetime nullable | Stamped on every price write (portal or staff) |
| `available_quantity` | decimal(15,4) nullable | Supplier; null = "unknown", ≠ 0; implicitly in `articles.unit` (shown read-only in portal) |
| `quantity_updated_at` | datetime nullable | Stamped on every quantity write |
| index `(article_id, is_active)` | | Public stock EXISTS probe |
| **partial unique index** `UNIQUE (article_id) WHERE is_preferred` | | DB is Postgres — enforce at-most-one preferred supplier per article at the schema level (today `limit(1)` is nondeterministic — pricing report) |

Plus a **`SupplierArticle` pivot model** (`app/Models/SupplierArticle.php`, extends `Pivot`) — required as Filament resource target and policy target under `strictAuthorization()`, and home for the `*_updated_at` touch logic. Add new columns to `withPivot` on `Article::suppliers()` / `Company::suppliedArticles()`; refactor the two admin relation managers onto it.

**`is_preferred` write path** *(revised per critique finding 9c — "enforced in the write path" now names its mechanism)*: one shared `App\Actions\SupplierArticles\SetPreferredSupplier` action (transaction: demote siblings, promote target), wired into **all three current writers** — the two admin relation managers' create/attach and edit forms — with the partial unique index above as the backstop. Migration first resolves existing multi-preferred articles deterministically (keep lowest `id`) before adding the index.

Field ownership: supplier edits `supplier_price`, `supplier_price_currency_id`, `available_quantity`, `lead_time_days` on own rows only. `is_preferred`, `is_active`, `notes`, `supplier_sku`, `last_quoted_*` stay staff-only.

### B2. `supplier_quotes` — RFQ/outcome state (no new tables; the RFQ *is* the PENDING `SupplierQuote`)

One migration:

| Column | Purpose |
|---|---|
| `submitted_via` enum (`internal`/`portal`), default `internal` | Supplier-typed vs staff-entered (`creator_id` is always internal staff today) |
| `submitted_at` datetime nullable + `submitted_by_user_id` FK nullOnDelete | Who/when the supplier actually responded |
| `declined_at` datetime nullable | "Decline to quote" (semantics fully specified below) |
| `sent_to_supplier_at` datetime nullable | **Portal visibility gate** *(new, per critique finding 2)* — stamped when `QuoteToSupplierMail` is successfully dispatched via "Send to Suppliers" (and backfilled from `notification_metadata` where a prior send is recorded). `RequestObserver`'s automatic `GenerateSupplierQuotesForRequest` on DRAFT→AWAITING_SUPPLIER_RESPONSE creates PENDING quotes for *every* matched active supplier with no per-supplier staff decision; without this gate, all candidate suppliers would see solicitations staff never issued. The portal shows **only** quotes with `sent_to_supplier_at` set. Staff-facing behavior unchanged. |

**Decline semantics** *(revised per critique finding 4 — the three interactions are now explicit rules)*: a declined quote stays `status = PENDING` (no new enum case — a new case touches every status consumer), with:

1. `CheckAwaitingSupplierQuotesJob` gains `whereNull('declined_at')` — declined quotes stop nagging staff. (This amendment is part of the same slice as `declined_at`; the original plan's rationale depended on it but never specified it.)
2. `checkAndUpdateExpiredStatus()` (and any expiry sweep) skips quotes with `declined_at` set — a declined quote never mutates to EXPIRED. Presenter precedence, in order: `declined_at` → "Declined", then status. Admin views get the same "Declined" badge derived from the timestamp.
3. **Re-solicitation resets a decline**: the "Send to Suppliers" `firstOrCreate` path, when it reuses an existing quote row for a supplier with `declined_at` set, clears `declined_at` (and `submitted_*`) and re-stamps `sent_to_supplier_at` — staff intent is a fresh RFQ, and the portal shows it as one.

**Won/lost — explicit REJECTED at a terminal event, not at selection** *(rewritten per critique findings 1 + 3; the original "REJECTED inside `applySelections()` + symmetric un-reject" design is withdrawn as unimplementable — `SupplierQuoteComparison::quotes()` and `QuotationEvaluation::syncSnapshotData()` both filter to `RECEIVED`/`SELECTED`, so a rejected loser would vanish from the matrix, be unreachable by any un-reject, and be silently excluded from post-selection QE snapshots; and each `markAsRejected()->save()` would trigger the observer's QE re-sync, which resets an APPROVED QE to NEED_APPROVAL).*

Design:

- `applySelections()` keeps its current semantics exactly (SELECTED winners, losers stay RECEIVED, re-apply freely toggles) — the evaluation phase remains fully reversible and no existing query breaks.
- New explicit staff action **"Announce outcomes"** (`App\Actions\SupplierPortal\AnnounceRfqOutcomes`), available on the comparison/QE once the decision is final; where a QE exists it is offered on (and gated by) QE approval, and it is also invoked-or-prompted from PO issuance. It: (a) calls the existing `markAsRejected()` on sibling RECEIVED quotes with zero selected items, (b) fires `SupplierQuoteOutcomeNotification` (won/lost) to portal suppliers, (c) marks the evaluation round closed (further `applySelections()` locked).
- Because REJECTED now exists post-decision, the two `RECEIVED/SELECTED` filters are widened to include `REJECTED` **for display**: the comparison matrix keeps showing losers (read-only) after announcement, and `syncSnapshotData()` keeps the full competitive picture. `SupplierQuoteObserver`'s QE re-sync gains a guard: outcome-only status transitions (→REJECTED via announce) do **not** trigger snapshot re-sync / approval reset.
- Notifications fire **only** from `AnnounceRfqOutcomes` — never from the raw SELECTED/REJECTED transition. This also inertizes the `obtained` shortcut (`SupplierQuoteObserver` PENDING→SELECTED on staff data entry): no announcement, no "won" mail.
- Portal rendering before announcement: submitted quotes show "Submitted — under review" regardless of internal SELECTED/RECEIVED churn; Won/Lost tabs populate only from announced outcomes (SELECTED/REJECTED *after* the round is closed — the presenter keys on announced state, and split awards still read from own items' `is_selected`).

### B3. `articles`

- `list_price` **decimal(15,4)** (tasks.md's 15,2 is wrong — match unit-price precision), nullable, team default currency. **Kept** as the human-published public price (see C).
- `list_price_updated_at` datetime nullable.
- `show_in_product_grid` boolean default false (as drafted).
- `price_review_needed` boolean default false *(new, per critique finding 7 — see §C staleness control)*.

### B4. Not added (unanimous cuts)

- **`teams.default_margin_percent` — dropped.** Duplicates `TeamErpSettings::$default_margin_percent` (default 3.0, live throughout buyer-quote seeding, existing settings UI).
- No RFQ/invitation tables, no `supplier_portal_users` table. (The `portal` column on `company_portal_users` per CP-2 replaces the previously-rejected separate-table idea at near-zero cost.)
- `config/auth.php`: add `supplier` session guard on the same `users` provider (config, not schema).

---

## C. Public catalog price + stock derivation

**Rule: the public catalog never derives at render time. It reads exactly one indexed column, `articles.list_price` (team default currency); null ⇒ "Price on request".** Unanimous. Live derivation is rejected doubly hard now: a supplier who maintains prices *and* browses the public site back-computes the exact margin (`public/mine = 1/(1−m)`), supplier edits would silently reprice the storefront, and nullable FX resolution would run on an unauthenticated page.

**Suggest-price (admin ArticleResource action, authenticated):**

1. **Cost** = preferred supplier's `supplier_price` → fallback preferred's `last_quoted_price` → fallback lowest *converted* `supplier_price` among active links (`pivot.is_active` AND `companies.is_supplier`) → none ⇒ no suggestion ("cost unknown").
2. **Currency**: `CurrencyService::convert(…, team passed explicitly)` to team default. *(Per critique finding 9b, the null-rate rule is now per-rung:)* for rungs 1–2 (single preferred-supplier cost), a missing rate ⇒ no suggestion with an explicit "missing exchange rate for {currency}" notice. For rung 3 (lowest across candidates), **skip unconvertible candidates and say so** — the notice lists skipped suppliers/currencies so the admin knows the minimum was computed over a subset; abort only if *no* candidate converts. Never copy cross-currency verbatim.
3. **Margin**: `MarginConvention::netUnitPrice(cost, TeamErpSettings::default_margin_percent)` — sell-based, `sell = cost / (1 − m/100)`.
4. Suggestion **only fills the input**; the admin edits/rounds and saves. **Saving is the publish act** and stamps `list_price_updated_at` and clears `price_review_needed`.

**Staleness control (the human-control answer to requirement 1)** *(revised per critique finding 7 — a cross-currency computed minimum cannot be a SQL table-filter constraint)*: the flag is **persisted** as `articles.price_review_needed` and recomputed at write time, not render time:

- Recompute triggers: `UpdateSupplierArticleOffer` (portal or staff supplier-price write → recompute for that article), `SetPreferredSupplier`, `list_price` save (clears it), and a lightweight daily scheduled command (`articles:refresh-price-review`) that catches FX-rate drift and `last_quoted_*` changes.
- Flag rule: `list_price` set AND (`MarginConvention::marginPercent(list_price, best converted cost) < default_margin_percent` OR best cost unconvertible).
- ArticleResource badge **and** table filter both read the persisted boolean — the filter is a plain indexed SQL constraint; no per-row PHP conversion on the admin list either.
- Supplier price edits *surface for review*; they never move a public number. No auto-repricing.

**Stock badge:** "In stock" ⇔ EXISTS active `supplier_articles` row with `available_quantity > 0` AND `companies.is_supplier = true` (both flags — a demoted supplier must not keep an article in stock), via `withExists`/select-subquery on the grid (never a per-card accessor). **Null quantity ⇒ "On request"** (third display state) — otherwise the entire catalog launches red until suppliers backfill. Recorded as an explicit decision, revisit after adoption.

**Coherence fix while touching it:** `GenerateSupplierQuotesForRequest::getLastQuotedPrice()` prefers `supplier_price` (currency respected) with `last_quoted_price` fallback, and stops copying prices across currencies blind. One loop: supplier maintains price → seeds their own RFQ lines → staff `last_quoted_*` history stays staff-owned.

---

## D. Supplier portal structure

**Structural mirror** of the post-refactor customer portal — same layering (provider/guard/session-cookie/context/policies/presenter/actions), same directory shape, same conventions. *(Per critique finding 8, stated honestly: this is a mirror of structure, not of names — customer-side legacy `Portal*` widget/notification names are deliberately kept in CP-1; supplier twins use the unambiguous `Supplier*` prefix.)* Everything stays in `app/` (module isolation makes `app-modules/` impossible; the customer portal lives in `app/`). Naming locked: resources `Supplier{Entity}Resource`, pages/widgets `Supplier*`, actions in `Actions/SupplierPortal/`.

```
app/Providers/Filament/SupplierPanelProvider.php        # id 'supplier', guard 'supplier', PanelDomain::supplierHost(),
                                                        #  strictAuthorization(), PortalPanelConfigurator::apply()
app/Support/PanelDomain.php                             # + supplierHost()
config/auth.php                                         # + 'supplier' guard, same 'users' provider
config/app.php                                          # + supplier_path / supplier_domain / supplier_session_cookie / supplier_portal_enabled
bootstrap/providers.php                                 # register before AppPanelProvider

app/Filament/Supplier/
├── Pages/
│   ├── Auth/SupplierLogin.php                          # CustomerLogin pattern incl. force-logout mount guard
│   ├── SupplierDashboard.php                           # 2 widgets (see below)
│   └── AcceptPortalInvitation.php                      # shared PortalInvitation flow, portal = supplier; creates a
│                                                       #  portal='supplier' membership row; marks email verified
├── Resources/
│   ├── SupplierArticleResource.php                     # model SupplierArticle; slug 'my-articles'
│   │   └── SupplierArticleResource/
│   │       ├── Pages/{ListSupplierArticles,EditSupplierArticle}.php   # article ident/unit read-only
│   │       └── Schemas/SupplierArticleForm.php         # ONLY the 4 supplier-writable fields
│   └── SupplierRfqResource.php                         # model SupplierQuote; slug 'rfqs'; label "Quote Requests"
│       └── SupplierRfqResource/
│           ├── Pages/{ListSupplierRfqs,ViewSupplierRfq,SubmitSupplierRfq}.php
│           │                                           # List: tabs Open/Submitted/Won/Lost (Won/Lost = announced only);
│           │                                           #  View: parent/child item hierarchy, own is_selected only;
│           │                                           #  Submit: per-item prices + validity + notes + quotation upload
│           │                                           #  + Decline action. NO currency-rate input (server-resolved).
│           └── Schemas/SupplierRfqSubmissionForm.php
└── Widgets/
    ├── SupplierOpenRfqsWidget.php                      # sent_to_supplier_at-gated, same scope as the resource
    └── SupplierStalePricesWidget.php                   # own rows with oldest *_updated_at
    └── (SupplierRfqOutcomesWidget.php — ships with the won/lost slice)

app/Services/Portal/{CustomerPortalContext,SupplierPortalContext}.php   # CP-3
app/Services/SupplierPortal/SupplierRfqStatusPresenter.php
    # precedence: declined_at→"Declined"; then PENDING→"Awaiting your quote", EXPIRED→"Expired";
    # RECEIVED and pre-announcement SELECTED→"Submitted — under review";
    # announced SELECTED→"Won", announced REJECTED→"Not selected"
app/Actions/SupplierPortal/
├── SubmitSupplierRfqResponse.php                       # same write as admin "Input price"; whitelisted fields;
│                                                       #  exchange_rate resolved SERVER-SIDE from ExchangeRate table
│                                                       #  (client-supplied values rejected — see §F); stamps
│                                                       #  submitted_via/at/by; observer PENDING→RECEIVED fires
│                                                       #  unchanged; uses AttachUploadedFiles
├── DeclineSupplierRfq.php                              # stamps declined_at; notifies internal team
├── AnnounceRfqOutcomes.php                             # terminal-event action (§B2): REJECTED on losers, outcome
│                                                       #  notifications, locks the evaluation round
├── UpdateSupplierArticleOffer.php                      # whitelisted 4 fields + *_updated_at stamps + price_review_needed
│                                                       #  recompute; admin RMs reuse it
└── InviteSupplierPortalUser.php                        # mirrors InvitePortalUser, portal='supplier'
app/Actions/SupplierArticles/SetPreferredSupplier.php   # shared transactional preferred-supplier writer (§B1)

app/Http/Middleware/EnsureSupplierPortalEnabled.php     # only NEW middleware; auth + session shared via CP-6
app/Policies/SupplierArticlePolicy.php                  # new
app/Policies/SupplierQuotePolicy.php                    # supplier-panel branch via ResolvesPanelContext
app/Notifications/SupplierQuoteOutcomeNotification.php  # won/lost, fired ONLY from AnnounceRfqOutcomes
app/Notifications/SupplierQuoteSubmittedNotification.php # → internal team (mirror of PortalRequestSubmittedNotification)
app/Mail/SupplierPortalUserInvitationMail.php
tests/Feature/SupplierPortal/                           # sibling dir, NOT appended to the 654-line customer file
openspec/specs/supplier-portal/spec.md                  # sibling of customer-portal spec
```

**Dashboard decision** — minimal dashboard (2 widgets): portal symmetry is an explicit requirement and the widgets are trivial over the CP-4-style scopes. **Adopting lean's other cuts:** no supplier self-registration (invite-only per D-goals), no payment-terms/tax editing in the portal v1 (staff refine on entry as today; notes field covers it), no stale-price nag notification, no company switcher work (shared context supports it free), RFQ invitation notification = the existing `QuoteToSupplierMail` gains a portal link (no new invitation mail class) — and the send path now also stamps `sent_to_supplier_at` (§B2).

---

## E. RFQ participation flow

The supplier-facing lifecycle maps 1:1 onto existing entities — no new tables:

| Supplier sees/does | Existing entity/mechanism |
|---|---|
| Receives RFQ | `SupplierQuote` `status=PENDING`, `supplier_id = own company`, **AND `sent_to_supplier_at` set** — auto-generated quotes from the stage-transition observer remain invisible until staff actually "Send to Suppliers" (the send stamps the timestamp and dispatches `QuoteToSupplierMail` with a portal deep-link). Prevents leaking sourcing activity staff never issued (critique finding 2). |
| Views RFQ items | `supplier_quote_items` → `request_items` (description, qty, unit, notes); parent/child hierarchy rendered (service quotes carry 0-priced child rows, shown read-only) |
| Submits quote | `SubmitSupplierRfqResponse` performs **the same write the admin "Input price" action performs**: per-item `unit_price`, currency, `valid_until`, notes, quotation document upload. **`exchange_rate` is never accepted from the client** — the admin form itself auto-resolves it as a Hidden field (`SupplierQuotesRelationManager.php:242-244`); the portal action resolves it server-side from the `ExchangeRate` table and strips/rejects any submitted value, since the rate drives `total_base` and comparison ranking (critique finding 6). `SupplierQuoteObserver`'s PENDING→RECEIVED auto-transition fires unchanged; all downstream comparison/QE/PO machinery just works. Stamps `submitted_via='portal'`, `submitted_at`, `submitted_by_user_id`. Internal team notified. |
| Declines | `declined_at` stamped; internal team notified; renders "Declined" (presenter precedence over EXPIRED); reminder job skips it; re-send by staff clears it (§B2 rules 1–3) |
| Tracks outcome | **Only announced outcomes.** During evaluation, submitted quotes read "Submitted — under review" regardless of internal SELECTED/RECEIVED toggling in `applySelections()`. On the terminal `AnnounceRfqOutcomes` action (offered at QE approval / PO issuance): SELECTED = won (something), REJECTED = lost; item-level own `is_selected` for split awards. `SupplierQuoteOutcomeNotification` fires exactly once, from the announce action — never from raw status transitions, so comparison re-apply churn and the `obtained` staff shortcut can't leak or spam (critique findings 1+3). |
| Never sees | Winner identity, winning prices, other suppliers' existence, unsent auto-generated RFQs — only their own sent rows and own `is_selected` flags (see F) |

Policy gates: `submit`/`decline` require own `supplier_id`, `status === PENDING`, `sent_to_supplier_at` set, `declined_at` null (for submit), and `valid_until` not past — the mirror of `BuyerQuotePolicy::respond()`.

---

## F. Scoping & confidentiality enforcement

Three layers, same as the customer portal:

**Layer 1 — query (what rows exist at all):**
- `SupplierArticle::scopeForSupplier($companyId)` in `SupplierArticleResource::getEloquentQuery()` + widgets.
- `SupplierQuote::scopeForSupplierPortal($companyId)` in `SupplierRfqResource::getEloquentQuery()` + widgets — own quotes, all statuses, **`whereNotNull('sent_to_supplier_at')`**. The send-gate lives in the scope so no portal surface can forget it.
- Company id always from `SupplierPortalContext` (session-validated, guard-parameterized, `portal='supplier'` membership); never request input. Never inline where-clauses (the CP-4 lesson).

**Layer 2 — policy (recomputed from DB per record, under `strictAuthorization()`):**
- `SupplierArticlePolicy`: view/update iff `record->supplier_id ∈ user->activeSupplierPortalCompanyIds()` (portal-typed memberships, CP-2); create/delete/attach denied (assignment is staff-only — requirement 2 is see/edit own rows, not manage the catalog).
- `SupplierQuotePolicy` supplier branch (via `ResolvesPanelContext`): view own+sent; submit/decline per §E gates.
- Field-level write confinement enforced **twice**: the portal form contains only supplier-writable fields, AND `UpdateSupplierArticleOffer`/`SubmitSupplierRfqResponse` whitelist attributes (form absence alone is not enforcement against tampered Livewire payloads). The whitelist **explicitly excludes `exchange_rate`** — always server-resolved (§E).
- Membership read live ⇒ deactivation takes effect immediately; `AuthenticatePanelUser` force-logs-out on lost `canAccessPanel('supplier')`.

**Layer 3 — projection (what renders):** portal resources define their own narrow schemas, never reuse admin ones. Never selected/rendered:
- Other suppliers' quotes/prices/**existence**; the comparison matrix, `bestPricesByItem`, and the QE snapshot JSON (embeds ALL suppliers' prices) are never loaded in the panel.
- Buyer identity: `requests.buyer_id`, buyer name/number/title context, buyer quotes/orders.
- Margins, sell prices, `list_price` linkage, P&L, QE status/approvers.
- Pre-announcement evaluation state: internal SELECTED/RECEIVED churn renders uniformly as "Submitted — under review".
- Unsent auto-generated RFQs (Layer 1 gate).
- `supplier_quotes.internal_notes` (already labeled "Not visible to supplier") and `notification_metadata`.
- On lost items: own `is_selected = false` only — never winner identity or winning price.
- `SupplierRfqStatusPresenter` isolates internal vocabulary (REJECTED → "Not selected").

Each "never see X" becomes a spec scenario in `openspec/specs/supplier-portal/spec.md` (symmetric extension of `erp-trading-core` §Supplier Confidentiality) backed by a feature test, including: session isolation, cross-portal access denial, **dual-role company person-level denial both directions (CP-2)**, **unsent-RFQ invisibility**, and **no outcome leak before announcement**.

---

## G. Required changes to `add-public-product-catalog`

| Decision | Status | Change |
|---|---|---|
| D1 (config-resolved team) | Keep | — |
| **D2** (stored `list_price` + suggestion) | **Amend** | Cost source = pivot `supplier_price` (fallback `last_quoted_price`), not `last_quoted_price` alone. **Drop `teams.default_margin_percent`** — reuse `TeamErpSettings::$default_margin_percent`; correct tasks.md items 7/11 and the `erp-trading-core` delta. `list_price` decimal(15,4) + `list_price_updated_at` + **persisted `price_review_needed`** with write-time recompute + daily FX-drift refresh command (the badge *and* filter read the persisted flag — a live cross-currency minimum is not SQL-expressible). Per-rung FX-failure rules for suggest-price (§C step 2). |
| **D3** (stock on pivot) | **Amend** | Add price columns alongside quantity (one migration); introduce `SupplierArticle` pivot model; derivation must filter `pivot.is_active` AND `companies.is_supplier`; add `(article_id, is_active)` index + partial unique `is_preferred` index + shared `SetPreferredSupplier` action; null quantity ⇒ "On request" (explicit decision, deviates from D3's binary rule). |
| D4 (registration table) | Keep | Add: approved buyers get an email-verification round-trip (application email was never verified); invitation acceptance marks email verified. |
| **D5** (supplier panel) | **Expand substantially** | Was stock-only/one-resource. Now: price + stock + lead-time self-service AND RFQ participation. `CompanyPortalUser` gains the `portal` type column (CP-2); `PortalInvitation` gains the `portal` enum (default `customer`, backfilled). D5's "structurally impossible" claim requires the CP-2 person-level split — without it, it's false for supplier-only users *and* for dual-role companies. |
| D6 (session cart) | Keep | — |
| D7 (Request from cart) | Keep | Note: catalog Requests flow into supplier-portal RFQs via the existing generate action — but portal visibility still requires the explicit staff send (`sent_to_supplier_at`). |
| D8 (homepage) | Keep | Header links three logins (customer/supplier/staff) — apex `/login` only knows the app panel. |
| **New D9** | Add | Customer-portal refactor-first as a separate, prior OpenSpec change (§A), including person-level `portal` membership typing. |
| **New D10** | Add | RFQ participation reuses `SupplierQuote` as the invitation (no new tables); `submitted_via/at/by`, `declined_at` (+ the three decline interaction rules), `sent_to_supplier_at` visibility gate; **outcome finalization via explicit `AnnounceRfqOutcomes` terminal action** (REJECTED on losers + single notification + widened RECEIVED/SELECTED display filters + observer re-sync guard), not selection-time status flips. |

New/updated OpenSpec artifacts: `refactor-customer-portal-for-portal-symmetry` change; `supplier-portal` capability spec; spec deltas to `erp-quoting` (announce-outcomes terminal event, REJECTED display-filter widening + QE re-sync guard, submission/decline/sent columns, reminder-job and expiry-sweep decline guards, prefill source + currency fix, server-side exchange-rate rule) and `erp-trading-core` (symmetric supplier-confidentiality requirement, margin-settings correction).

---

## H. Phased delivery plan

Each slice independently deployable; kill-switch flags gate the catalog and supplier panel.

1. **Slice 0 — Customer-portal refactor** (CP-1…CP-7, including the `portal` columns on `portal_invitations` **and** `company_portal_users` with `'customer'` defaults/backfill and the widened unique index). Own OpenSpec change, zero behavior change except the CP-2 security fix, existing tests green. Gates everything.
2. **Slice 1 — Data model + admin plumbing.** `supplier_articles` migration (incl. preferred-dedup + partial unique index) + `SupplierArticle` model + `withPivot` updates + `SetPreferredSupplier`; admin relation managers gain the new fields via `UpdateSupplierArticleOffer` (staff can enter supplier prices/quantities before any portal exists); `GenerateSupplierQuotesForRequest` prefill switch + currency fix. Additive, zero UI risk.
3. **Slice 2 — Supplier panel shell + article self-service.** Guard, provider, config, `supplierHost()`, shared session/auth middleware entries, invitation flow (portal-typed membership), login, dashboard (2 widgets), `SupplierArticleResource` + policy, tests. Delivers requirements 1 (supplier side) + 2 behind `supplier_portal_enabled`.
4. **Slice 3 — RFQ participation.** `supplier_quotes` migration (`submitted_*`, `declined_at`, `sent_to_supplier_at` + backfill from `notification_metadata`); send-path stamping; **the three decline rules** (reminder-job `whereNull('declined_at')`, expiry-sweep skip, re-send reset); `SupplierRfqResource` + submit/decline actions (server-resolved exchange rate) + presenter + quotation upload (via `AttachUploadedFiles`); portal link in `QuoteToSupplierMail`; submitted-notification to team. Admin flow untouched. Delivers requirement 3 (minus outcomes). Portal shows "Submitted — under review" for all post-submit states. |
5. **Slice 4 — Won/lost via announce.** `AnnounceRfqOutcomes` action (REJECTED on losers, single outcome notification, round lock), wired to QE approval / PO issuance; widen `SupplierQuoteComparison::quotes()` and `QuotationEvaluation::syncSnapshotData()` to include REJECTED for display; `SupplierQuoteObserver` re-sync guard for outcome-only transitions; outcomes widget; Won/Lost tabs live. Shipped separately because it changes admin-visible status semantics and comparison behavior post-announcement — announce to the team with the new rule: "losers become REJECTED only when outcomes are announced; the matrix keeps showing them."
6. **Slice 5 — Catalog price plumbing** (parallel track, depends only on Slice 1): `articles.list_price` (15,4) + `show_in_product_grid` + `list_price_updated_at` + `price_review_needed` + refresh command, Suggest-price action, needs-review badge/filter on the persisted flag. Admin-only.
7. **Slice 6 — Public catalog + cart + buyer registration** (draft's D1/D4/D6/D7/D8 with §G amendments), reading only `list_price` + the stock EXISTS aggregate.

Ordering note: supplier-portal-first retained; the catalog proceeds in parallel any time after Slice 1 with staff-maintained pivot data.

---

## Rejected critique points

None rejected outright. Two findings were resolved by choosing one of the critique's offered alternatives rather than both: (1) finding 1 — chose *terminal-event REJECTED* (announce action) **plus** the display-filter widening and re-sync guard, rather than widening filters around selection-time rejection, because selection-time rejection also collides with finding 3's notification timing; (8) finding 8 — chose the honest "mirror of structure, not legacy names" wording over renaming customer `Portal*` widgets, to keep Slice 0 mechanical (rename listed as an optional deferred cosmetic). Finding 5 reverses an original design decision (company-flags-only access), accepted in full: person-level `portal` membership typing on `company_portal_users` (Postgres confirmed; unique index widened to `(company_id, user_id, portal)`), making the invitation `portal` enum load-bearing.

---

# Appendix: Verification Critique (all findings applied or resolved)

All claims verified. Findings, ranked:

1. **HIGH — Slice 4's "explicit REJECTED on losers" breaks the QE evidence trail and the comparison re-open flow; the promised "symmetric un-reject" is impossible as specified.**
   Evidence: `app/Livewire/SupplierQuoteComparison.php:224-231` — `quotes()` loads only `RECEIVED`/`SELECTED`. Once a loser is `REJECTED` it vanishes from the price matrix AND from the `applySelections()` loop (`:182-196`), so the plan's "symmetric un-reject (back to RECEIVED) on re-apply" can never execute — the rejected quote is not in `$this->quotes`. Worse: `app/Models/QuotationEvaluation.php:446-448` — `syncSnapshotData()` also filters `RECEIVED`/`SELECTED`, so a QE created *after* selection (which is the actual flow — "Create QE" requires a SELECTED quote to exist) would snapshot only the winners, destroying the competitive comparison the 3-role approval exists to review. And `app/Observers/SupplierQuoteObserver.php:104-108` re-syncs all QEs on *any* status change, while `syncSnapshotData()` (`QuotationEvaluation.php:433-438`) resets an APPROVED QE to NEED_APPROVAL and clears all three approval timestamps — each `markAsRejected()->save()` per loser can nuke an approved QE. The plan announces this slice as a mere admin-label change ("RECEIVED losers become REJECTED in admin views"); it is actually a state-machine change that current queries are incompatible with. Fix: either set REJECTED at a *terminal* event (QE approval or PO issuance), or widen both `RECEIVED/SELECTED` filters (comparison + QE snapshot) to include REJECTED and guard the QE-reset path — the plan must specify which; today it specifies neither.

2. **HIGH — Suppliers see RFQs that were never sent to them.** `app/Observers/RequestObserver.php:83-116` auto-runs `GenerateSupplierQuotesForRequest` on DRAFT→AWAITING_SUPPLIER_RESPONSE, creating PENDING quotes for **every** active supplier of every matched article — with no email and no staff decision per supplier. `QuoteToSupplierMail` is only sent via the explicit "Send to Suppliers" path (Path B). The plan's Layer 1 rule is "all own quotes, all statuses; nothing else excluded" (§F) and §E's mail hook only covers Path B — so the moment the stage flips, all candidate suppliers see "Awaiting your quote" in the portal for solicitations staff never issued (revealing sourcing activity, and inviting quotes staff meant to prune or delete). The portal query needs a sent/visible gate (e.g., successful send recorded in `notification_metadata`, or a `sent_at` column) — nothing in the plan or slices provides one.

3. **HIGH — Outcome notifications fire prematurely and repeatedly.** §E: `SupplierQuoteOutcomeNotification` "fired on the SELECTED/REJECTED transition via the observer." But `markAsSelected()` runs inside `applySelections()` (`SupplierQuoteComparison.php:184`) — i.e. *before* QE creation and the 3-role approval, and the existing code already demotes SELECTED→RECEIVED on re-apply (`:191-195`); the plan's own un-reject makes toggling explicit. A supplier would be told "won" during an open internal evaluation, then possibly "lost" as staff iterate — leaking a non-final decision to a counterparty and spamming on every re-apply. Also the `obtained` shortcut (`SupplierQuoteObserver.php:73-78`) jumps PENDING→SELECTED on staff data entry, which would also fire "won." Notify on a definitive event (QE approved / PO issued / explicit "announce outcomes" action), not the raw status transition.

4. **MEDIUM — The decline design contradicts its own stated rationale and leaves declined quotes in a semantic muddle.** The plan keeps declined quotes at status PENDING and justifies rejecting "no decline" because "`CheckAwaitingSupplierQuotesJob` would keep nagging staff." But the job queries `status = PENDING` only (`app/Jobs/Erp/CheckAwaitingSupplierQuotesJob.php:46-50`) — under the chosen design it **still nags declined quotes forever**; no `whereNull('declined_at')` amendment appears anywhere in the plan or slices. Additionally: `checkAndUpdateExpiredStatus()` flips PENDING past `valid_until` to EXPIRED, so a declined quote later reads "Expired" in admin and the presenter's declined-vs-EXPIRED precedence is unspecified; and the "Send to Suppliers" `firstOrCreate` path reuses the existing quote row when staff re-solicit/add items, leaving `declined_at` stamped on what staff believe is a fresh RFQ. All three interactions need explicit rules.

5. **MEDIUM — Dual-role company = silent privilege escalation, and the new `portal` invitation column is decorative.** Access derived purely from `companies.is_buyer`/`is_supplier` (both flags confirmed independent, `app/Models/Company.php:45-46,96-97`) means a person invited as a *buyer contact* of a dual-role company automatically gains supplier-portal capabilities — editing `supplier_price`/`available_quantity`, viewing and submitting RFQs, receiving outcomes — and vice versa. These are different capabilities intended for different individuals at the counterparty; "same company's own data on both sides" glosses over person-level roles. Internally inconsistent too: CP-1 adds a `portal` enum to `portal_invitations` recording which portal the person was invited to, then ignores it for authorization — the invited-as-customer signal exists in the schema and is deliberately not enforced. Either enforce invitation intent (a `portal_type` on membership, as domain-flow proposed) or explicitly spec the escalation with a test proving it's intended; "accepted behavior" in one sentence is not adequate for a confidentiality boundary.

6. **MEDIUM — Supplier-controlled `exchange_rate` can game the comparison.** §E lists "currency + rate" among fields the supplier submits, mirroring the admin "Input price" write. But in the admin form the rate is a `Hidden` field auto-resolved from the `ExchangeRate` table (`SupplierQuotesRelationManager.php:242-244`) — staff don't free-type it either. `exchange_rate` directly drives `total_base` and `bestPricesByItem` ranking (`SupplierQuoteComparison.php:297`). If the portal accepts a supplier-supplied rate (or a tampered Livewire payload sets one), a supplier understates the rate to rank cheapest in base currency. The plan's whitelist principle (§F) doesn't mention `exchange_rate`; the spec must state the portal resolves the rate server-side and rejects client-provided values.

7. **MEDIUM — The "needs price review" *table filter* is not implementable as specified.** §C flags rows where `MarginConvention::marginPercent(list_price, best converted cost) < default_margin_percent`. "Best converted cost" requires per-row FX conversion across arbitrary currencies (`CurrencyService::convert()` is PHP, nullable, cached per pair) — fine for a badge column computed per rendered row, but a Filament **table filter** must produce a SQL constraint; a cross-currency computed minimum cannot be expressed in the query without materializing converted costs. The plan bans render-time derivation on the public page then quietly institutes an N×M conversion on the admin list plus an impossible SQL filter. Needs either a persisted `review_needed`/converted-cost column maintained on writes, or downgrade the filter to badge-only.

8. **LOW — The "exact mirror, one token substituted" claim is self-violated.** Plan D asserts every supplier path is the customer path with one token substituted, but CP-1 deliberately keeps customer widgets/notifications named `Portal*` (`PortalRequestsOverviewWidget`, `PortalBuyerQuoteSentNotification`) while supplier twins are `Supplier*`; and the customer form moves to `Schemas/CustomerRequestForm.php` while its pre-refactor pages/widgets keep legacy names. Deliberate, but the symmetry claim should be stated honestly ("mirror of structure, not of the Portal-prefixed legacy names") or the customer widgets renamed `Customer*` in CP-1 — the plan currently does neither.

9. **LOW — Unstated migration details.** (a) `portal_invitations.portal` enum needs a `'customer'` default/backfill for existing unaccepted invitations or the accept flow breaks on in-flight invites. (b) Suggest-price rung 3 ("lowest *converted* `supplier_price`") doesn't say what happens when *one* candidate lacks an FX rate — step 2's "null ⇒ no suggestion" rule read literally aborts the whole suggestion because of one unconvertible minor supplier; skipping the candidate silently changes which cost wins. Specify skip-with-notice or abort. (c) `is_preferred` "enforced in the write path" names no location — there are at least three writers (two admin RMs' create/attach/edit forms); a partial unique index (`article_id` where `is_preferred`) or a single shared action should be named, or the nondeterminism survives.

Verified and sound (no findings): pivot table has an `id` PK so the `SupplierArticle` pivot-model/Filament-resource approach works (`2026_01_12_184251...php:17`); the CP-2 leak is real exactly as claimed (`User.php:151-156,188-192`); `AuthenticateAppPanel`/`AuthenticateCustomerPanel` differ only by class name (diff confirms — CP-6a is safe); `TeamErpSettings::$default_margin_percent = 3.0` exists so dropping `teams.default_margin_percent` is right; `MarginConvention::netUnitPrice`/`marginPercent` signatures match §C's usage; the 654-line test file exists; the path-prefix→cookie `UsePanelSession` generalization is compatible with the actual `UseCustomerPanelSession` mechanics (path always set even with a custom domain); keeping `list_price` as a human-published snapshot (§C) is well-grounded; and the B1 column split (`supplier_price` vs `last_quoted_price`) matches the codebase's actual write-ownership.

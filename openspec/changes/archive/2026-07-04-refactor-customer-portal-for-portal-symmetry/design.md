# Design: Customer portal refactor for portal symmetry

Full investigation evidence and the critique that shaped these decisions: `openspec/changes/add-public-product-catalog/architecture.md` (§A, critique findings 5, 8).

## Context

The customer portal is the only portal today; a supplier portal will mirror it. Whatever is duplicated or wrong in the customer portal gets copied. This change fixes the pattern once, with zero externally visible behavior change except the CP-2 access fix.

## Decisions

### R1. Rename only colliding symbols; keep legacy names elsewhere
`PortalContext`/`InitializePortalContext`/`activePortalCompanyIds()` collide with their future supplier twins and are renamed with a `Customer` prefix. Tables, models (`CompanyPortalUser`, `PortalInvitation`), and customer-side `Portal*` widget/notification class names are kept — the supplier portal mirrors *structure*, not legacy names. Renaming those too would bloat the diff for zero behavior. (Cosmetic renames deferred, non-blocking.)

### R2. Person-level portal membership (security fix)
Deriving portal capability from `companies.is_buyer`/`is_supplier` alone has two confirmed holes: a supplier-only membership passes `canAccessPanel('customer')` today, and at a dual-role company any invited person would hold both portals' capabilities implicitly. Fix: `portal` enum (`customer`/`supplier`) on both `portal_invitations` and `company_portal_users` (default `'customer'`, backfilled; membership unique index → `(company_id, user_id, portal)`). Invitation intent becomes load-bearing: accept writes the invitation's `portal` into the membership. Access = active membership of that portal type AND the matching company flag. One person at a dual-role company can hold both memberships — but only by explicit invitation to each.

### R3. Two thin context classes, not one contextually-bound class
`CustomerPortalContext` now, `SupplierPortalContext` later, sharing a private parameterized core (guard string, membership portal type + company flag, session-key prefix). Filament's static-heavy code resolves services by class name, so two concrete classes beat conditional container bindings. Also removes the `?? auth()->user()` default-guard fallback (latent cross-panel leak).

### R4. Column scoping lives in named scopes
`Request::scopeForBuyer()` / `Shipment::scopeForBuyerCompany()` replace 4 hand-written where-clauses (resource + 3 widgets). Convention the supplier portal repeats (`SupplierQuote::scopeForSupplierPortal()` etc.): portal surfaces never inline company-id where-clauses.

### R5. Panel discrimination by auth guard
`ResolvesPanelContext` policy concern discriminates by guard (fallback: panel id) — guards are 1:1 with portals after this change, and guard checks work outside Filament page lifecycles (queued jobs, API).

### R6. Share plumbing by composition, and stop
Four extractions (merged panel-auth middleware, config-driven session-cookie middleware, `AttachUploadedFiles` action, `PortalPanelConfigurator` static configurator). Explicitly rejected: base panel provider classes, panel factories, moving portals to `app-modules/` (module-isolation rules forbid the dependencies portals need: MarginConvention, currency services, Request flow), per-panel policy classes.

### R7. `Schemas/` convention
Form schema moves off the Create page into `CustomerRequestResource/Schemas/CustomerRequestForm.php` — the layout both portals use.

## Risks / Migration

- Backfill defaults keep in-flight invitations and existing memberships working (`'customer'`).
- CP-2 tightens access: verify no legitimate supplier-company membership exists in production data before deploy (there should be none — the portal is buyer-only today).
- The 654-line `CustomerPortalTest` suite is the safety net; it must pass unchanged apart from new dual-role assertions.

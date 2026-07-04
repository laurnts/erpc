# Change: Refactor customer portal into the canonical portal pattern (pre-supplier-portal)

## Why

A supplier portal is planned (`add-public-product-catalog`) that must mirror the customer portal's structure. An architecture audit (see `openspec/changes/add-public-product-catalog/architecture.md`) found the customer portal's skeleton sound — panel + dedicated guard + session-cookie isolation + `company_portal_users` membership + panel-branched policies under `strictAuthorization()` — but identified naming that won't generalize, four copy-paste smells that would be duplicated into a second portal, and one confirmed **security bug**: portal access is derived from company flags alone, so a supplier-only company's membership passes `canAccessPanel('customer')` (`app/Models/User.php:151-156`), and at a dual-role (buyer+supplier) company any invited person would implicitly hold both portals' capabilities. These must be fixed once, before the pattern is copied.

## What Changes

- **CP-1 Rename colliding symbols only**: `PortalContext` → `CustomerPortalContext`, `InitializePortalContext` → `InitializeCustomerPortalContext`, `activePortalCompanyIds()` → `activeCustomerPortalCompanyIds()`. Table/model names (`company_portal_users`, `CompanyPortalUser`, `PortalInvitation`) and legacy customer-side `Portal*` widget/notification names are kept.
- **CP-2 Security fix — person-level portal membership**: `company_portal_users` and `portal_invitations` each gain a `portal` enum column (`customer`/`supplier`), default `'customer'` with backfill; membership unique index widens to `(company_id, user_id, portal)`. Invitation `portal` flows into the membership on accept. Access checks become two-condition: `hasActiveBuyerPortalAccess()` = active `portal = customer` membership AND `companies.is_buyer` (supplier twin added later). Company switcher filtered the same way.
- **CP-3 Context service**: `app/Services/Portal/CustomerPortalContext.php` (final readonly), guard-parameterized private core; kills the hardcoded guard call and the `?? auth()->user()` default-guard fallback.
- **CP-4 Scopes as single source of truth**: `Request::scopeForBuyer()`, `Shipment::scopeForBuyerCompany()` replace the 4 hand-written `buyer_id` where-clauses.
- **CP-5 Policy concern**: `App\Policies\Concerns\ResolvesPanelContext` (guard-discriminated, panel-id fallback) replaces 3 private copies in `RequestPolicy`/`BuyerQuotePolicy`/`ShipmentPolicy`.
- **CP-6 Shared plumbing, composition only**: merge byte-identical `AuthenticateAppPanel`/`AuthenticateCustomerPanel` → `AuthenticatePanelUser`; generalize `UseCustomerPanelSession` → `UsePanelSession` (config map path-prefix → cookie); extract FileUpload→media loop → `App\Actions\Media\AttachUploadedFiles`; add `App\Support\PortalPanelConfigurator::apply(Panel): Panel` (~40 lines shared defaults). No inheritance, no panel factory.
- **CP-7 Form off the Create page**: `CreateCustomerRequest::formComponents()` → `CustomerRequestResource/Schemas/CustomerRequestForm.php`, establishing the `Schemas/` convention.

No behavior change except CP-2 (the security fix).

## Impact

- Affected specs: `customer-portal` (MODIFIED: Customer Portal User Access — portal-typed membership)
- Affected code: `app/Models/User.php`, `app/Models/CompanyPortalUser.php`, `app/Models/PortalInvitation.php`, `app/Services/Portal/`, `app/Http/Middleware/` (panel auth/session), `app/Policies/{Request,BuyerQuote,Shipment}Policy.php`, `app/Filament/Customer/**`, `app/Actions/CustomerPortal/InvitePortalUser.php`, 2 migrations (backfill included)
- Tests: `tests/Feature/CustomerPortal/CustomerPortalTest.php` must stay green; new tests for dual-role denial in both directions
- **Blocks**: `add-public-product-catalog` supplier-portal slices — this change lands first

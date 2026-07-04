# Tasks: refactor-customer-portal-for-portal-symmetry

Sequential; each task leaves the suite green.

- [x] 1. Migration: add `portal` enum (`customer`/`supplier`) to `portal_invitations` and `company_portal_users`, default `'customer'`, backfill existing rows; widen `company_portal_users` unique index to `(company_id, user_id, portal)`
- [x] 2. CP-2 access checks: split `User::hasActivePortalAccess()` into `hasActiveBuyerPortalAccess()` (active `portal=customer` membership AND `companies.is_buyer`); update `canAccessPanel('customer')`; filter `activeMemberships()`/company switcher per portal type; `InvitePortalUser` + `AcceptPortalInvitation` carry `portal` through to the membership row
- [x] 3. CP-2 tests: dual-role company denial both directions; supplier-only membership denied on customer panel; invitation portal type flows into membership
- [x] 4. CP-1 renames: `PortalContext` → `CustomerPortalContext` (move to `app/Services/Portal/`), `InitializePortalContext` → `InitializeCustomerPortalContext`, `activePortalCompanyIds()` → `activeCustomerPortalCompanyIds()`; update all references
- [x] 5. CP-3: parameterize the context core (guard string, portal type + company flag, session-key prefix); remove hardcoded `auth()->guard('customer')` and the `?? auth()->user()` fallback
- [x] 6. CP-4: add `Request::scopeForBuyer()` and `Shipment::scopeForBuyerCompany()`; replace the 4 inline `buyer_id` where-clauses (CustomerRequestResource + 3 widgets)
- [x] 7. CP-5: extract `App\Policies\Concerns\ResolvesPanelContext` (guard-discriminated, panel-id fallback); adopt in `RequestPolicy`, `BuyerQuotePolicy`, `ShipmentPolicy`
- [x] 8. CP-6a/b: merge `AuthenticateAppPanel`/`AuthenticateCustomerPanel` → `AuthenticatePanelUser`; generalize `UseCustomerPanelSession` → `UsePanelSession` driven by config map (path prefix → cookie); update both panel providers
- [x] 9. CP-6c/d: extract `App\Actions\Media\AttachUploadedFiles` from the duplicated FileUpload→media loops; add `App\Support\PortalPanelConfigurator::apply(Panel): Panel` and use it in `CustomerPanelProvider`
- [x] 10. CP-7: move `CreateCustomerRequest::formComponents()` → `CustomerRequestResource/Schemas/CustomerRequestForm.php`
- [x] 11. Validation: `vendor/bin/pint --dirty`; `composer test:types`; full `php artisan test --compact` green incl. `tests/Feature/CustomerPortal/CustomerPortalTest.php`; `openspec validate refactor-customer-portal-for-portal-symmetry --strict`

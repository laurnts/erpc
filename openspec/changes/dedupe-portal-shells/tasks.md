# Tasks: dedupe-portal-shells

> Depends on `add-public-product-catalog` being committed and archived. Each task ends with the customer + supplier portal suites green.

## 1. Shared shell (DP-1)

- [x] 1.1 Extract `App\Services\Portal\PortalContext` interface (`company()`, `companies()` → shipped as `activeMemberships()` plus `teamId()`/`companyId()`/`team()`/`clear()`, matching the facades' real surface); `CustomerPortalContext` and `SupplierPortalContext` implement it
- [x] 1.2 Move brand name/logo/favicon resolvers and the `switchPortalCompany` user-menu action into `PortalPanelConfigurator::apply()` (new `context`, `dashboard`, `guestBrandName` parameters — the switch label is identical in both portals, the guest brand name is what actually varies); moved `filament/customer/brand-logo.blade.php` → `filament/portal/brand-logo.blade.php`
- [x] 1.3 Rewired `CustomerPanelProvider` and `SupplierPanelProvider` to the parameterized configurator; deleted the duplicated resolver/action blocks (providers are now id/domain/guard/discovery only); test asserts both panels render the shared `filament.portal.brand-logo` view

## 2. Shared accept flow (DP-2)

- [x] 2.1 Extracted `App\Actions\Portal\AcceptPortalInvitation::execute()` (TDD: `tests/Feature/Portal/AcceptPortalInvitationActionTest.php` covers user creation with verified email, supplier portal-type copy, and existing-user reuse without credential modification)
- [x] 2.2 Both accept pages call the action; per-panel `PortalType` token filters kept; added the supplier-side `does not resolve customer-typed invitation tokens on the supplier accept page` test

## 3. Shared invite path (DP-3)

- [x] 3.1 `App\Actions\Portal\InvitePortalUser` takes `PortalType`, folds in the per-portal company-role guard; `PortalUserInvitationMail` + single Blade view derive subject/copy from `$invitation->portal`
- [x] 3.2 Rewired staff call sites (`ViewBuyer`, `ViewSupplier` invite actions — the buyer `PortalUsersRelationManager` had no direct invite call); deleted `InviteSupplierPortalUser`, `SupplierPortalUserInvitationMail`, and the duplicate mail view; both portal suites drive the one action

## 4. Naming + policy symmetry (DP-4, DP-5)

- [x] 4.1 Renamed `User::hasActiveBuyerPortalAccess()` → `hasActiveCustomerPortalAccess()` across all call sites (model, policies, middleware, catalog page, tests)
- [x] 4.2 Added `userOwnsSupplierCompany()` to `ResolvesPanelContext`; `SupplierArticlePolicy` uses the concern (private `ownsRow()` deleted); `SupplierQuotePolicy::ownsAsSupplier()` delegates to it

## 5. Validation

- [x] 5.1 `vendor/bin/pint --dirty` clean; PHPStan zero errors on all authored/reworked files; portal suites (117 tests) + full `php artisan test --compact` green
- [x] 5.2 `openspec validate dedupe-portal-shells --strict` passes; both portal specs update on archive

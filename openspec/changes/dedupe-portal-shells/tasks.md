# Tasks: dedupe-portal-shells

> Depends on `add-public-product-catalog` being committed and archived. Each task ends with the customer + supplier portal suites green.

## 1. Shared shell (DP-1)

- [ ] 1.1 Extract `App\Services\Portal\PortalContext` interface (`company()`, `companies()`, `setCompany()`); have `CustomerPortalContext` and `SupplierPortalContext` implement it
- [ ] 1.2 Move brand name/logo/favicon resolvers and the `switchPortalCompany` user-menu action into `PortalPanelConfigurator::apply()` (new `context`, `dashboard`, `switchLabel` parameters); move `filament/customer/brand-logo.blade.php` → `filament/portal/brand-logo.blade.php`
- [ ] 1.3 Rewire `CustomerPanelProvider` and `SupplierPanelProvider` to the parameterized configurator; delete the duplicated resolver/action blocks; assert both providers contain no branding logic (test: both panels render the shared brand-logo view)

## 2. Shared accept flow (DP-2)

- [ ] 2.1 Extract `App\Actions\Portal\AcceptPortalInvitation::execute()` from the customer page's transaction (TDD: port the existing accept tests to drive the action directly, covering existing-user reuse and portal-typed membership)
- [ ] 2.2 Switch both accept pages to the action; keep per-panel `PortalType` token filters; add the supplier-side test mirroring `does not resolve customer-typed invitation tokens on the supplier accept page`

## 3. Shared invite path (DP-3)

- [ ] 3.1 Add `PortalType` parameter to `InvitePortalUser`, move it to `app/Actions/Portal/`, fold in the role guard per portal; parameterize `PortalUserInvitationMail` (subject/URL/copy from `$invitation->portal`) and the mail Blade view
- [ ] 3.2 Rewire staff call sites (buyer `PortalUsersRelationManager`, `ViewSupplier` invite action); delete `InviteSupplierPortalUser`, `SupplierPortalUserInvitationMail`, and the duplicate mail view; invite tests cover both portals through the one action

## 4. Naming + policy symmetry (DP-4, DP-5)

- [ ] 4.1 Rename `User::hasActiveBuyerPortalAccess()` → `hasActiveCustomerPortalAccess()` across all call sites
- [ ] 4.2 Add `userOwnsSupplierCompany()` to `ResolvesPanelContext`; convert `SupplierArticlePolicy` (and Slice 3 supplier RFQ policies) to the concern; delete private `ownsRow()` copies

## 5. Validation

- [ ] 5.1 `vendor/bin/pint --dirty`; PHPStan clean on touched files; full portal suites + `php artisan test --compact` green
- [ ] 5.2 `openspec validate dedupe-portal-shells --strict`; update both portal specs on archive

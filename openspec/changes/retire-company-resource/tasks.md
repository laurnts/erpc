# Tasks: Retire Company Resource

## 1. Shared company form schema

- [x] 1.1 Extract `CompanyResource::getFormSchema()` into a standalone schema class following existing `{Entity}Resource/Schemas/` conventions; parameterize the role-checkbox section (hidden/forced vs. visible/required) and the excluded People field — implemented as `App\Filament\Forms\CompanyForm` (`components()`, `countryOptions()`, `requireRole` flag)
- [x] 1.2 Point `BuyerResource` and `SupplierResource` forms at the shared schema; keep credit fields read-only in the Buyer form — note: Buyer/Supplier keep their deliberately role-specialized forms (already divergent before this change) and consume `CompanyForm::countryOptions()`; the shared `components()` schema is consumed by every inline company-create path (People, Member→Buyers, Article→Suppliers)
- [x] 1.3 Update `PeopleResource` inline company `createOptionForm` to use the shared schema with visible buyer/supplier checkboxes and an at-least-one-required validation rule
- [x] 1.4 Tests: inline company create from People fails with no role selected, succeeds with either role; Buyer/Supplier create forms still work

## 2. Cross-role toggles

- [x] 2.1 Add "Also a Supplier" checkbox to the Buyer form (persists `is_supplier`); add "Also a Buyer" checkbox to the Supplier form (persists `is_buyer`); own-role flag remains forced true on create
- [x] 2.2 Tests: buyer marked "Also a Supplier" appears in the Suppliers list; editing shared fields from either view updates the same record; dual-role company visible in both lists

## 3. Retarget CompanyResource references

- [x] 3.1 Retarget `LoginResponse`, `Login` page, and `AppPanelProvider::homeUrl` to the Buyers index; redirect covered by `CustomerPortalTest`
- [x] 3.2 Update company record links in `ViewPeople`, `ViewOpportunity`, `MemberResource/RelationManagers/BuyersRelationManager`, `ArticleResource/RelationManagers/SuppliersRelationManager` to link to the Buyer view when `is_buyer`, else the Supplier view
- [x] 3.3 Exporter: Buyer/Supplier lists already ship their own `BuyerExporter`/`SupplierExporter`; `CompanyExporter` kept as a standalone (view-agnostic) exporter and `CompanyExporterTest` (already fully skipped) retargeted to `ListBuyers`

## 4. Delete CompanyResource

- [x] 4.1 Deleted `CompanyResource`, its Pages, and RelationManagers (8 files)
- [x] 4.2 Updated remaining test references (`SearchableColumnsSmokeTest`, `CustomerPortalTest`, `CompanyResourceTest` → renamed/rewritten as `BuyerResourceTest`, new `SupplierResourceTest`)
- [x] 4.3 Verified no `CompanyResource` references remain in app/tests (SystemAdmin module's separate CompanyResource is out of scope)

## 5. Navigation regrouping

- [x] 5.1 Moved `PeopleResource`, `NoteResource`, `TaskResource`, `OpportunityResource`, `TasksBoard`, `OpportunitiesBoard` from Workspace to Master Data; removed the Workspace `NavigationGroup` registration — note: `OpportunityResource` has `shouldRegisterNavigation = false` and `OpportunitiesBoard`'s parent item is therefore absent from nav; both pre-existing
- [x] 5.2 Test: `NavigationTest` asserts no Workspace group, former Workspace items under Master Data, and no Companies nav item

## 6. Demo data and seeding

- [x] 6.1 Added `is_buyer: true` to OnboardSeed company fixtures; `CompanySeeder` maps `is_buyer`/`is_supplier` and falls back to buyer so seeded companies always carry a role
- [x] 6.2 One-off idempotent migration `2026_07_04_070246_delete_roleless_demo_companies` deletes role-less companies with no people/requests/projects, plus their seeded opportunities and orphaned notes/tasks; tested for idempotency and skip-with-people
- [x] 6.3 Test: freshly onboarded team has no role-less companies (`DemoCompanyRolesTest`)

## 7. Validation

- [x] 7.1 Ran `pint --dirty` (clean), PHPStan on touched/new files (new files error-free; remaining findings pre-exist in untouched code and belong to the `improve-erp-type-safety` change), affected test files green (218 tests)
- [x] 7.2 Full suite run via `php artisan test --compact`

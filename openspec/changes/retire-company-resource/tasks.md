# Tasks: Retire Company Resource

## 1. Shared company form schema

- [ ] 1.1 Extract `CompanyResource::getFormSchema()` into a standalone schema class following existing `{Entity}Resource/Schemas/` conventions; parameterize the role-checkbox section (hidden/forced vs. visible/required) and the excluded People field
- [ ] 1.2 Point `BuyerResource` and `SupplierResource` forms at the shared schema; keep credit fields read-only in the Buyer form
- [ ] 1.3 Update `PeopleResource` inline company `createOptionForm` to use the shared schema with visible buyer/supplier checkboxes and an at-least-one-required validation rule
- [ ] 1.4 Tests: inline company create from People fails with no role selected, succeeds with either role; Buyer/Supplier create forms still work

## 2. Cross-role toggles

- [ ] 2.1 Add "Also a supplier" checkbox to the Buyer form (persists `is_supplier`); add "Also a buyer" checkbox to the Supplier form (persists `is_buyer`); own-role flag remains forced true on create
- [ ] 2.2 Tests: buyer marked "Also a supplier" appears in the Suppliers list; editing shared fields from either view updates the same record; unchecking the cross-role flag removes it from the other list

## 3. Retarget CompanyResource references

- [ ] 3.1 Retarget `LoginResponse` and `AppPanelProvider::homeUrl` to the Buyers index; test login redirect
- [ ] 3.2 Update company record links in `ViewPeople`, `ViewOpportunity`, `PeopleResource`, `MemberResource/RelationManagers/BuyersRelationManager`, `ArticleResource/RelationManagers/SuppliersRelationManager` to link to the Buyer view when `is_buyer`, else the Supplier view
- [ ] 3.3 Wire `CompanyExporter` to the Buyer/Supplier list export actions (or confirm existing wiring); update `CompanyExporterTest`

## 4. Delete CompanyResource

- [ ] 4.1 Delete `CompanyResource`, its Pages, and any orphaned Schemas/Tables classes; remove imports
- [ ] 4.2 Update or retarget remaining test references (`SearchableColumnsSmokeTest`, `CustomerPortalTest`, Filament resource tests)
- [ ] 4.3 Verify no `CompanyResource` references remain (`grep -r CompanyResource app tests`)

## 5. Navigation regrouping

- [ ] 5.1 Move `PeopleResource`, `NoteResource`, `TaskResource`, `OpportunityResource` and the `TasksBoard`/`OpportunitiesBoard` pages from the Workspace group to Master Data; remove any Workspace group registration/sort config
- [ ] 5.2 Test: navigation contains no Workspace group; Master Data lists Buyers, Suppliers, Articles, Tags, People, Notes, Tasks, Opportunities and the two boards

## 6. Demo data and seeding

- [ ] 6.1 Add role flags to OnboardSeed company fixtures so seeded companies are always buyer and/or supplier; adjust OnboardSeed logic if fixtures don't support the flags yet
- [ ] 6.2 One-off cleanup (migration or artisan command): delete role-less companies with no people/transactions, including their seeded polymorphic notes/tasks/opportunities; test the cleanup is idempotent and skips companies with data
- [ ] 6.3 Test: freshly onboarded team has no role-less companies

## 7. Validation

- [ ] 7.1 Run `vendor/bin/pint --dirty`, `composer test:types`, and the affected test files; fix regressions
- [ ] 7.2 Run full suite `php artisan test --compact` before marking the change complete

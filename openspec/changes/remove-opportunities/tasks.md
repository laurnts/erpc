# Tasks: Remove Opportunities

## 1. Detach references from surviving entities

- [x] 1.1 Removed `opportunities()` relations from `Company`, `Team`, and `User`; removed the opportunity morph relations from `Task` and `Note`; Note/Task forms had no opportunity fields (the exclude-param usage was a no-op); removed the opportunities count column from `CompanyExporter`
- [x] 1.2 Removed `buildOpportunityContext()`, `formatOpportunities()`, and related helpers from `RecordContextBuilder`; removed `addOpportunities()` from `RecordSummaryService`; removed opportunities from `InvalidatesRelatedAiSummaries`
- [x] 1.3 Removed custom-fields integration: `OpportunityField` enum deleted, `CreateTeamCustomFields` map + union types updated, `config/custom-fields.php` entry removed, `BackfillCustomFieldColorsCommand` restricted to Task fields
- [x] 1.4 Removed the `'opportunity'` morph map entry and the Opportunity TeamScope wiring in `ApplyTenantScopes`

## 2. Delete Opportunity code

- [x] 2.1 Deleted `OpportunityResource` (+ Pages, Forms, RelationManagers), `OpportunitiesBoard`, `OpportunityImporter`, `OpportunityExporter`
- [x] 2.2 Deleted SystemAdmin `OpportunityResource` (+ Pages) and `OpportunityPolicy`; deleted `SalesAnalyticsChartWidget` (entirely pipeline-based); stripped pipeline/opportunity stats from `BusinessOverviewWidget` and `TeamPerformanceTableWidget`
- [x] 2.3 Deleted `Opportunity` model, `OpportunityFactory`, `OpportunityObserver` (attribute-registered, no separate registration), `App\Policies\OpportunityPolicy`
- [x] 2.4 Deleted OnboardSeed `OpportunitySeeder`, its sequence entry, the 4 fixture YAMLs, the NoteSeeder type-map entry, and the fixture template reference

## 3. Database cleanup

- [x] 3.1 Migration `2026_07_04_094354_remove_opportunities_entity`: purges opportunity-typed rows (both `'opportunity'` alias and FQCN forms) from `noteables`/`taskables`/`ai_summaries` and custom-fields tables (values, options, definitions, sections), then drops the table; also added a `Schema::hasTable` guard to the earlier `2026_07_04_070246` cleanup migration so its test harness re-run survives the drop
- [x] 3.2 Ran locally; verified table gone and zero opportunity custom-field rows remain

## 4. Tests and docs

- [x] 4.1 Deleted `OpportunityResourceTest`; removed opportunity cases from `CompanyTest`, `TeamTest`, `SearchableColumnsSmokeTest`, `RecordSummaryServiceTest`; reworked `DemoCompanyRolesTest` without the Opportunity factory
- [x] 4.2 Notes/tasks coverage for remaining entities retained (`RecordSummaryServiceTest` note/task context tests, Note/Task resource tests — all green)
- [x] 4.3 Stripped opportunity feature descriptions from the 4 Documentation module guides and `resources/docs/USER_GUIDE.md`
- [x] 4.4 Reference sweep clean — remaining matches are generic English prose in two demo fixture descriptions and an email template

## 5. Validation

- [x] 5.1 pint clean; PHPStan on touched files shows no new errors (3 remaining findings pre-date this change in untouched code); affected test files green (126 tests)
- [x] 5.2 Full suite `php artisan test --compact`

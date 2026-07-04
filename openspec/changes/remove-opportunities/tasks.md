# Tasks: Remove Opportunities

## 1. Detach references from surviving entities

- [ ] 1.1 Remove `opportunities()` relations from `Company` and `Team`; remove the opportunity morph relation from `Task` and the opportunity noteable relation from `Note`; drop opportunity options/params from `NoteForm` and `TaskForm` (and any Note/Task resource columns or filters that surface opportunities)
- [ ] 1.2 Remove `buildOpportunityContext()` and opportunity branches from `RecordContextBuilder`/`RecordSummaryService`
- [ ] 1.3 Remove custom-fields integration: `App\Enums\CustomFields\OpportunityField`, the `CreateTeamCustomFields` map entry, the `config/custom-fields.php` entry, and opportunity handling in `BackfillCustomFieldColorsCommand`
- [ ] 1.4 Remove the `'opportunity'` morph map entry and the `Opportunity` TeamScope wiring in `ApplyTenantScopes`

## 2. Delete Opportunity code

- [ ] 2.1 Delete app Filament surfaces: `OpportunityResource` (+ Pages, Forms, RelationManagers), `OpportunitiesBoard`, `OpportunityImporter`, `OpportunityExporter`
- [ ] 2.2 Delete SystemAdmin surfaces: `OpportunityResource` (+ Pages) and `OpportunityPolicy`; strip opportunity/pipeline metrics from `BusinessOverviewWidget` and `SalesAnalyticsChartWidget`
- [ ] 2.3 Delete `App\Models\Opportunity`, `OpportunityFactory`, `OpportunityObserver`, `App\Policies\OpportunityPolicy` (and observer/policy registrations)
- [ ] 2.4 Delete OnboardSeed `OpportunitySeeder`, its `$entitySeederSequence` entry, and the 4 opportunity fixture YAMLs

## 3. Database cleanup

- [ ] 3.1 New idempotent migration: delete opportunity-typed rows from `noteables`/`taskables`, opportunity custom-field definitions/options/values, opportunity AI summary rows; then drop the `opportunities` table
- [ ] 3.2 Run the migration locally and verify the table and residue are gone

## 4. Tests and docs

- [ ] 4.1 Delete `OpportunityResourceTest`; remove opportunity cases from `CompanyTest`, `TeamTest`, `SearchableColumnsSmokeTest`, `RecordSummaryServiceTest`; rework `DemoCompanyRolesTest` to assert without the Opportunity factory
- [ ] 4.2 Add/keep a test asserting notes and tasks still work for their remaining entities (people/companies) after the morph removal
- [ ] 4.3 Strip opportunity feature descriptions from the Documentation module guides
- [ ] 4.4 Verify no `Opportunit*` references remain in `app/`, `app-modules/` (excluding archived openspec content), `config/`, `database/factories/`, `tests/`

## 5. Validation

- [ ] 5.1 `pint --dirty`, PHPStan on touched files (no new errors), affected test files green
- [ ] 5.2 Full suite `php artisan test --compact`

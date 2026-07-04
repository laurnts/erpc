# Remove Opportunities — Retire the CRM Deals Pipeline

## Why

Opportunities (deals with pipeline stages) are Relaticle CRM inheritance with no role in the ERP trading workflow — the Request → Quote → Order pipeline covers deal tracking. The entity is already half-retired in practice: `OpportunityResource` has navigation unregistered, the OpportunitiesBoard is invisible (its parent nav item doesn't exist), and the only data ever created was OnboardSeed demo content, which the retire-company-resource cleanup already deleted. The `opportunities` table is empty with zero note/task links. What remains is dead weight: a model, two Filament surfaces, importer/exporter, AI summary wiring, custom-field definitions, seeder fixtures, SystemAdmin surfaces, and tests.

## What Changes

- **Delete the Opportunity entity end to end**: model, factory, observer, policies (app + SystemAdmin), morph map entry, TeamScope wiring in `ApplyTenantScopes`.
- **Delete all Filament surfaces**: `OpportunityResource` (+ pages, form, relation managers), `OpportunitiesBoard`, `OpportunityImporter`, `OpportunityExporter`, and the SystemAdmin `OpportunityResource` (+ pages).
- **Remove references from other entities**: `opportunities()` relations on Company and Team, the opportunity morph relation on Task, the opportunity noteable relation on Note, and any opportunity options in Note/Task forms.
- **Remove custom-fields integration**: `OpportunityField` enum, `CreateTeamCustomFields` map entry, `config/custom-fields.php` entry, opportunity handling in `BackfillCustomFieldColorsCommand`.
- **Remove AI summary support**: `buildOpportunityContext()` in `RecordContextBuilder` and opportunity branches in related services.
- **Remove OnboardSeed content**: `OpportunitySeeder`, its sequence entry, and the 4 opportunity fixture YAMLs.
- **Drop the database footprint** via a new migration: drop the `opportunities` table and delete opportunity-typed rows from polymorphic pivots (`noteables`, `taskables`), custom-fields tables, and AI summaries. Historical migrations stay untouched (fresh installs create then drop).
- **SystemAdmin widgets**: remove opportunity/pipeline metrics from `BusinessOverviewWidget` and `SalesAnalyticsChartWidget`.
- **Tests**: delete `OpportunityResourceTest`; update Company/Team model tests, `SearchableColumnsSmokeTest`, `DemoCompanyRolesTest` (uses an Opportunity factory), and `RecordSummaryServiceTest`.
- **Docs**: strip opportunity mentions from the Documentation module guides where they describe live functionality.

## Impact

- Affected specs: `crm-core` (Opportunity Management removed; Task Management and Note Management scenarios trimmed), `erp-trading-core` (Company Role Classification scenarios trimmed)
- Affected code: ~40 files deleted or edited across `app/`, `app-modules/SystemAdmin/`, `app-modules/OnboardSeed/`, `app-modules/Documentation/`, `config/`, `database/`, `tests/`
- Data: `opportunities` table dropped — currently 0 rows locally and 0 polymorphic links; the drop migration is destructive by design and deletes opportunity custom-field definitions/values on all teams
- Out of scope: the Flowforge board package stays (TasksBoard uses it); Notes/Tasks/People keep their other polymorphic targets

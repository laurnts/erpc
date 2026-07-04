# Remove Tasks & Notes — Retire the Remaining CRM Activity Entities

## Why

Tasks and Notes are Relaticle CRM inheritance with no role in the ERP trading workflow. The user has confirmed they are not used at all: the database holds 4 tasks and 1 note (demo/seed leftovers). The standalone nav items sit misfiled under "Master Data" (they are activity records, not reference data), the Tasks Board (Flowforge kanban) has no audience, and every surviving surface — relation managers on People/Requests, AI summary context, custom fields, SystemAdmin resources, OnboardSeed fixtures — is dead weight. This completes the CRM retirement started by `remove-opportunities` (archived 2026-07-04), which already removed the third activity entity.

## What Changes

- **Delete the Task and Note entities end to end**: models, factories, observers, policies (app + SystemAdmin), morph map entries (`'task'`, `'note'`), TeamScope wiring in `ApplyTenantScopes`, and the `HasNotes` concern.
- **Delete all Filament surfaces**: `TaskResource` and `NoteResource` (+ Pages, Forms), `TasksBoard` (Flowforge kanban), `NoteExporter`, the `TasksRelationManager`/`NotesRelationManager` under `PeopleResource` (also registered on `RequestResource` and `ViewPeople`), and the SystemAdmin `TaskResource`/`NoteResource` (+ pages).
- **Remove references from surviving entities**: `tasks()`/`notes()` relations and `HasNotes` usage on `Company`, `People`, `Request`, `Team`; `tasks()` on `User`.
- **Remove custom-fields integration**: `TaskField` and `NoteField` enums, `CreateTeamCustomFields` map entries + union types, `config/custom-fields.php` entries, and `BackfillCustomFieldColorsCommand` (its sole purpose is Task status/priority colors).
- **Remove AI summary support**: notes/tasks context in `RecordContextBuilder`, `addNotes()`/`addTasks()` in `RecordSummaryService`, and the `InvalidatesRelatedAiSummaries` trait if Note/Task were its only consumers.
- **Remove OnboardSeed content**: `TaskSeeder`, `NoteSeeder`, their sequence entries, and the task/note fixture YAMLs.
- **Drop the database footprint** via a new migration: drop `tasks`, `notes`, `taskables`, `noteables`, `task_user` and purge task/note-typed rows from custom-fields tables and `ai_summaries`. Historical migrations stay untouched (fresh installs create then drop); guard earlier migrations/tests that touch these tables where re-runs would break.
- **SystemAdmin widgets**: remove the Task Completion stat from `BusinessOverviewWidget`.
- **Flowforge dependency**: remove `relaticle/flowforge` from composer — `TasksBoard` was its only consumer.
- **Tests**: delete `TaskResourceTest`/`NoteResourceTest`; update `NavigationTest` (Notes/Tasks labels gone from Master Data), `SearchableColumnsSmokeTest`, `RecordSummaryServiceTest`, `CompanyTest`, `TeamTest`, `UserTest`, `DemoCompanyRolesTest`, and `RemoveOpportunitiesEntityMigrationTest` (its migration re-run touches the now-dropped pivots).
- **Docs**: strip task/note feature descriptions from the Documentation module guides where they describe live functionality.

## Impact

- Affected specs: `crm-core` (Task Management and Note Management removed; People Management remains as the capability's scope)
- Affected code: ~55 files deleted or edited across `app/`, `app-modules/SystemAdmin/`, `app-modules/OnboardSeed/`, `app-modules/Documentation/`, `config/`, `database/`, `tests/`, `composer.json`
- Data: `tasks` (4 rows), `notes` (1 row), and their pivots dropped — destructive by design, confirmed unused by the owner; task/note custom-field definitions/values deleted on all teams
- Out of scope: Tags stay in Master Data (used by Articles); the `people` entity and its remaining relation managers stay

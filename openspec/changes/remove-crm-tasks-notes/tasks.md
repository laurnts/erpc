# Tasks: Remove Tasks & Notes

## 1. Detach references from surviving entities

- [x] 1.1 Removed `tasks()`/`notes()` relations and `HasNotes` usage from `Company`, `People`, `Request`, `Team`; removed `tasks()` from `User`; deleted the `HasNotes` concern
- [x] 1.2 Removed notes/tasks context from `RecordContextBuilder` (company + person builders, `formatNotes()`/`formatTasks()`, pagination/date/html helpers); removed `addNotes()`/`addTasks()`/`formatTaskLine()` from `RecordSummaryService`; deleted `InvalidatesRelatedAiSummaries` (Note/Task were its only consumers)
- [x] 1.3 Removed custom-fields integration: `TaskField` + `NoteField` enums deleted, `CreateTeamCustomFields` map + union types updated, `config/custom-fields.php` entries removed, `BackfillCustomFieldColorsCommand` deleted (its sole purpose was Task status/priority colors)
- [x] 1.4 Removed the `'task'`/`'note'` morph map entries in `AppServiceProvider` and the Task/Note TeamScope wiring in `ApplyTenantScopes`

## 2. Delete Task and Note code

- [x] 2.1 Deleted `TaskResource` + `NoteResource` (Pages, Forms), `TasksBoard`, `NoteExporter`, and `TasksRelationManager`/`NotesRelationManager` (deregistered from `PeopleResource`, `ViewPeople`, `RequestResource`; also removed ViewPeople's stale `BuyersRelationManager` import)
- [x] 2.2 Deleted SystemAdmin `TaskResource`/`NoteResource` (+ Pages) and SystemAdmin `TaskPolicy`/`NotePolicy`; stripped the Task Completion stat from `BusinessOverviewWidget`; reworked `TeamPerformanceTableWidget` to companies-only (its query hit the dropped tables); deleted the now-orphaned `HasCustomFieldQueries` trait
- [x] 2.3 Deleted `Task` + `Note` models, `TaskFactory`/`NoteFactory`, `TaskObserver`/`NoteObserver`, `App\Policies\TaskPolicy`/`NotePolicy`
- [x] 2.4 Deleted OnboardSeed `TaskSeeder`/`NoteSeeder`, their sequence entries in `OnboardSeedManager`, and the task/note fixture YAML directories
- [x] 2.5 Removed `relaticle/flowforge` via `composer remove` (TasksBoard was the only consumer); removed the flowforge `@source` and `.flowforge-column-header` rules from the app panel theme CSS

## 3. Database cleanup

- [x] 3.1 Migration `2026_07_04_113447_remove_tasks_and_notes_entities`: purges task/note-typed rows (alias + FQCN forms) from `ai_summaries` and custom-fields tables (values, options, definitions, sections), then drops `taskables`, `noteables`, `task_user`, `tasks`, `notes`; irreversible `down()` per precedent
- [x] 3.2 Guarded re-run hazards: `Schema::hasTable` guards added to `2026_07_04_094354_remove_opportunities_entity` (noteables/taskables) and `2026_07_04_070246_delete_roleless_demo_companies` (both pivots); rewrote `2025_08_25_173222_update_order_column_to_flowforge_position` without the removed Flowforge package (Rank backfill only mattered for already-migrated DBs; tables are dropped later anyway)
- [x] 3.3 Ran locally; verified all five tables gone and zero task/note custom-field or ai_summary rows remain

## 4. Tests and docs

- [x] 4.1 Deleted `TaskResourceTest`/`NoteResourceTest`; updated `NavigationTest` (Notes/Tasks/Board asserted absent), `SearchableColumnsSmokeTest`, `RecordSummaryServiceTest`, `CompanyTest`, `TeamTest`, `UserTest`, `DemoCompanyRolesTest`; extended `RemoveOpportunitiesEntityMigrationTest` with coverage for the new drop migration
- [x] 4.2 Stripped task/note feature descriptions from the Documentation module guides (api, business, quick-start, technical) and `resources/docs/USER_GUIDE.md`
- [x] 4.3 Reference sweep clean — remaining matches are a historical-migration comment and intentional string literals in the migration test

## 5. Validation

- [x] 5.1 pint clean; PHPStan on touched files shows no new errors (remaining findings pre-date this change in untouched portal-refactor code)
- [x] 5.2 Full suite `php artisan test --compact` green

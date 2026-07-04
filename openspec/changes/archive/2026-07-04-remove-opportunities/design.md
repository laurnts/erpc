# Design: Remove Opportunities

## Context

Opportunity is a soft-deleted, team-scoped CRM entity with AI summaries, custom fields (amount, close date, stage), polymorphic notes/tasks, a Kanban board (Flowforge), CSV import/export, and a SystemAdmin mirror resource. It is functionally orphaned: app-panel navigation for it is unregistered, the board page is unreachable, and the table is empty after the retire-company-resource demo cleanup.

## Goals / Non-Goals

- **Goals:** zero remaining Opportunity code paths, schema, or stored data; Notes/Tasks/AI/custom-fields keep working for their remaining entities.
- **Non-Goals:** removing the Flowforge dependency (TasksBoard uses it); rewriting archived OpenSpec changes (history stays as written); renaming the generic word "opportunity" in prose like email templates.

## Decisions

### 1. Hard removal, including the table

The table is empty and the entity is unreachable in the UI, so we drop `opportunities` outright rather than keeping a dormant schema. A single new migration drops the table and clears opportunity-typed residue: `noteables`/`taskables` pivot rows, custom-fields definitions/options/values scoped to the Opportunity entity, and AI summary rows. Historical migrations are left untouched — on a fresh install they create and later drop the table, which is harmless and preserves migration history integrity. The migration is idempotent (guards with `Schema::hasTable()` / typed where-clauses).

### 2. Custom-fields cleanup goes through the package's own storage

`relaticle/custom-fields` stores definitions per entity type. Deleting the `OpportunityField` enum and the `CreateTeamCustomFields` map stops new teams from getting opportunity fields; the migration deletes existing definitions (and cascading options/values) for the opportunity entity type so no orphaned definitions linger in team custom-field settings.

### 3. SystemAdmin widgets lose pipeline metrics

`BusinessOverviewWidget` and `SalesAnalyticsChartWidget` currently blend opportunity counts/pipeline value with ERP metrics. The opportunity stats are removed; the widgets keep their remaining metrics. This is the one user-visible change for system admins.

### 4. Morph map entry removed, pivot rows deleted first

Removing `'opportunity'` from the enforced morph map would make any surviving `noteable_type = 'opportunity'` row throw on hydration — which is why the migration deletes pivot residue and runs in the same change. Order of operations in deployment: code deploy (map removed) is safe because the pivots are already empty in every environment that ran the retire-company-resource cleanup; the new migration guarantees it.

### 5. Tests updated, not weakened

`OpportunityResourceTest` is deleted with its subject. `DemoCompanyRolesTest` swaps its Opportunity fixture for a Note-only assertion (the migration under test also handled opportunities; that branch is now unreachable and its assertion is dropped). Company/Team relation tests lose their `opportunities()` cases. `RecordSummaryServiceTest` loses the opportunity context/invalidation cases; people/company summary coverage remains.

## Risks / Trade-offs

- **Destructive migration:** any non-demo opportunity data in a deployed environment would be lost. Current evidence says none exists (0 rows, 0 links); the migration deletes rather than archives. Accepted per decision to drop the capability.
- **Retire-company-resource cleanup migration** (`2026_07_04_070246`) references the `opportunities` table; it always runs before the drop migration (timestamp order), so fresh installs are unaffected.
- **Archived specs/changes reference opportunities** — left as historical record, standard OpenSpec practice.

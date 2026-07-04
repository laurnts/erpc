# CRM Core Delta

## REMOVED Requirements

### Requirement: Task Management
**Reason**: Tasks are unused Relaticle CRM inheritance (4 demo rows). The ERP workflow tracks work through Requests, Quotes, and Orders; no team plans standalone tasks, and the Tasks Board kanban has no audience.
**Migration**: The Task model, Filament surfaces (resource, board, relation managers), custom fields, seeder fixtures, SystemAdmin resource, and AI summary support are deleted; `tasks`, `taskables`, and `task_user` tables are dropped and task-typed polymorphic/custom-field rows are purged by an idempotent migration. The `relaticle/flowforge` dependency is removed with its only consumer.

### Requirement: Note Management
**Reason**: Notes are unused Relaticle CRM inheritance (1 row). Entity-level annotations live in dedicated fields (e.g. company `internal_notes`) and workflow records; the polymorphic Note entity has no users.
**Migration**: The Note model, Filament surfaces (resource, relation managers, exporter), custom fields, seeder fixtures, SystemAdmin resource, and AI summary support are deleted; `notes` and `noteables` tables are dropped and note-typed polymorphic/custom-field rows are purged by an idempotent migration.

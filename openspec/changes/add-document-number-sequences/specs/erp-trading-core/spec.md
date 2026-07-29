## MODIFIED Requirements

### Requirement: Acceptance Report Team Scoping
Acceptance reports SHALL be team-scoped like all other document-owning ERP entities: `acceptance_reports` carries a `team_id` foreign key (cascade on delete), the model uses `HasTeam`, is registered in the enforced morph map, and new report numbers are sequenced per team per year with a matching `(team_id, report_number)` unique constraint.

#### Scenario: Team assigned on creation
- **WHEN** an acceptance report is created for a request
- **THEN** its `team_id` matches the parent request's team
- **AND** records are isolated per team via Filament panel tenancy and the `HasTeam` relationship

#### Scenario: Existing reports backfilled
- **WHEN** the migration runs on existing data
- **THEN** every existing acceptance report receives the `team_id` of its parent request
- **AND** the unique index moves from `(request_id, report_number)` to `(team_id, report_number)`

#### Scenario: Report numbering scoped per team and year, allocated from a locked counter
- **WHEN** a new acceptance report number is generated
- **THEN** the sequence increments per team per year (format `AR-{year}-{seq:04d}`), allocated from a locked counter row (`document_number_sequences`) rather than by reading the highest existing `report_number`
- **AND** previously issued report numbers are never rewritten or reissued, including when a report is deleted or when rows are inserted out of order

#### Scenario: Morph map registration repairs attachments
- **WHEN** an attachment is uploaded to an acceptance report
- **THEN** the media record persists using the `acceptance_report` morph alias on the `local` disk
- **AND** the upload no longer fails silently via a swallowed morph-map violation

## ADDED Requirements

### Requirement: Document Number Allocation
Request (`request_number`) and Project (`project_number`) numbers SHALL be allocated from a locked counter row (one row per team, document key, and calendar year in `document_number_sequences`) rather than by reading the highest existing number, so that concurrent creates cannot receive the same number and the sequence does not regress once a team's yearly count passes 9999 (a string-sorted "highest number" query would otherwise rank `'9999'` above `'10000'`). Numbers are strictly monotonic per (team, key, year): a rolled-back or deleted document permanently skips its number rather than having that number reissued to a later document.

#### Scenario: Concurrent request creates do not collide
- **WHEN** two requests are created for the same team at effectively the same time
- **THEN** each receives a distinct, correctly incrementing `request_number`
- **AND** neither create fails due to a duplicate-number save

#### Scenario: Sequence does not regress past 9999
- **WHEN** a team's project count for a year is already at 9999
- **THEN** the next allocated `project_number` sequence value is 10000, not a value already issued

#### Scenario: 30 rapid acceptance report allocations never collide
- **WHEN** 30 acceptance report numbers are allocated in immediate succession for the same team
- **THEN** all 30 numbers are distinct

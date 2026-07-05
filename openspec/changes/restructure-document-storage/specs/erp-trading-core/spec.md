# erp-trading-core Spec Delta

## ADDED Requirements

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

#### Scenario: Report numbering scoped per team and year
- **WHEN** a new acceptance report number is generated
- **THEN** the sequence increments per team per year (format `AR-{year}-{seq}`) using max-sequence semantics
- **AND** previously issued report numbers are never rewritten

#### Scenario: Morph map registration repairs attachments
- **WHEN** an attachment is uploaded to an acceptance report
- **THEN** the media record persists using the `acceptance_report` morph alias on the `local` disk
- **AND** the upload no longer fails silently via a swallowed morph-map violation

# erp-trading-core Delta

## ADDED Requirements

### Requirement: Deal and Company Delete Protection via Financial Documents
The system SHALL prevent hard-deleting a `Request` or a `Company` once it has produced any
financial document anywhere in the deal chain (a buyer or supplier order, a buyer or supplier
invoice, a buyer or supplier payment, or a profit-and-loss record): the database SHALL reject the
delete via `RESTRICT` foreign keys on the documenting tables, and the `Request`/`Company` row SHALL
remain. Records in this state must be archived (soft-deleted) rather than hard-deleted; only a
`Request`/`Company` that has never produced a financial document can be force-deleted. Deleting a
`Team`, by contrast, remains a full cascade: every `team_id` foreign key on requests, companies,
and their financial documents is untouched `cascadeOnDelete()`, so a team delete removes the team
and everything under it in one statement, since a team delete represents intentional account
closure rather than routine record cleanup. This is safe specifically because PostgreSQL queues
referential-integrity checks as after-statement triggers: within the single `DELETE FROM teams`
statement, the cascades to `requests`, `companies`, and their financial documents (all still
`team_id` `CASCADE`) execute first, and only then do the RESTRICT triggers on e.g.
`buyer_invoices.request_id` fire — by which point the referencing `buyer_invoices` row has already
been removed by the team-id cascade, so there is no orphan left for the RESTRICT check to catch.
A direct `DELETE`/force-delete of the `requests` row on its own is a separate statement and has no
such cascade running ahead of it, so the RESTRICT trigger sees the still-live `buyer_invoices` row
and rejects it.

#### Scenario: A request with a financial document cannot be hard-deleted
- **WHEN** a request that has produced a buyer invoice (or any other financial document) is
  force-deleted
- **THEN** the database rejects the delete and the request row remains

#### Scenario: A company cannot be hard-deleted while its requests carry financial documents
- **WHEN** a buyer or supplier company whose request has produced a financial document is
  force-deleted
- **THEN** the delete is rejected once it reaches the protected document, and neither the company
  nor the request row is removed

#### Scenario: A request or company with no financial documents can still be hard-deleted
- **WHEN** a request (or company) that has never produced an order, invoice, payment, or
  profit-and-loss record is force-deleted
- **THEN** the delete succeeds and the row is removed

#### Scenario: Deleting a team still removes everything under it
- **WHEN** a `Team` is deleted
- **THEN** all of its requests, companies, and their financial documents are removed via
  cascading `team_id` foreign keys, in one statement, regardless of how many financial documents
  exist
- **AND** this succeeds even though the same rows are protected by a RESTRICT foreign key,
  because PostgreSQL fires the `team_id` CASCADE triggers before the RESTRICT triggers within the
  single delete statement, so the RESTRICT check never observes a referencing row

Verified directly against PostgreSQL 17 in a throwaway database mirroring the real constraint
shape (`teams` ← `requests` `ON DELETE CASCADE`; `buyer_invoices` → `teams` `CASCADE`, →
`requests` `RESTRICT`): `DELETE FROM requests WHERE id = 1` raises
`buyer_invoices_request_id_fkey ... still referenced from table "buyer_invoices"`, while
`DELETE FROM teams WHERE id = 1` succeeds and leaves `teams`, `requests`, and `buyer_invoices` all
empty. There is no automated regression test for the team-cascade half of this (see tasks.md).

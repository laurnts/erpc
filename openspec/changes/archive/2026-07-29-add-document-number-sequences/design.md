## Context

Fourteen document types across the demand and supply sides (`Request`, `Project`, `BuyerQuote`, `BuyerOrder`, `BuyerInvoice`, `BuyerPayment`, `SupplierQuote`, `SupplierOrder`, `SupplierInvoice`, `SupplierPayment`, `Shipment`, `AcceptanceReport`, `QuotationEvaluation`, `ProfitAndLoss`) each generated their own sequential number, independently, by reading `ORDER BY <column> DESC LIMIT 1` (or, for `QuotationEvaluation`/`ProfitAndLoss`, `ORDER BY id DESC`) and adding one. Every number column already carried a unique index (`(team_id, <column>)` or `(request_id, <column>)`), so this was cross-cutting: a fix touching one generator without touching the rest would leave the same race and the same lexical-sort bug live in 13 other places.

This document is retroactive: the code is on `master`. It records the decisions so a future reader does not have to reverse-engineer them from the diff.

## Goals / Non-Goals

- Goals:
  - Make "get the next number for (team, document kind, year)" atomic and race-free under concurrent creates.
  - Fix the string-sort bug (`'9999' > '10000'` lexically) that silently broke every create once a team crossed 10,000 documents of one kind in a year.
  - Fix the `QuotationEvaluation`/`ProfitAndLoss` id-ordering bug where an out-of-order insert could reset the count.
  - Change nothing about the 14 existing number *formats* — every prefix, separator, roman-numeral month, and suffix rule stays exactly as before.
- Non-Goals:
  - Not touching `Shipment::do_number` (the Delivery Order PDF number), which remains on the old read-max scheme. It was out of scope for this pass; it is a candidate for a follow-up change.
  - Not building a generic cross-format number formatter. See Decisions below.
  - Not attempting true reuse of numbers left as gaps by rollbacks or deletions — see the trade-off below.

## Decisions

### Decision: A locked counter row, not `SELECT MAX(...) + 1`

`document_number_sequences` holds one row per `(team_id, key, period)`, unique on that triple, with a `next_value` column. `DocumentNumberAllocator::next()` opens a transaction, `SELECT ... FOR UPDATE`s the row (or inserts it if absent), reads `next_value`, increments it, and returns the pre-increment value. Two concurrent callers for the same counter now serialise on the row lock instead of both reading the same "current max" and racing to insert the same number.

The one race that remains is the very first allocation for a brand-new counter row: two concurrent callers can both find no row and both attempt to `INSERT`. The unique index on `(team_id, key, period)` rejects the loser with a `QueryException`; `next()` catches that specific case (SQLSTATE `23505` on PostgreSQL, `23000` + a "unique" substring on SQLite — the suite runs both, so both must be recognised) and retries once, which now finds the winner's row and takes the lock path. This is a narrow, self-healing retry, not a retry loop around arbitrary failures.

### Decision: Formatting stays at the call site

The four document-number formats in this system are structurally different: dashed (`PREFIX-YYYY-NNNN`), PO with an optional trailing letter suffix for same-request repeat orders (`PO-YYYY-NNNN-A`), and two roman-numeral-month formats with the sequence number in different positions (`NNN-DS/QE/{roman}/YYYY` vs `NNNN/EL-PNL/{roman}/YYYY`). A generic formatter parameterised over all four shapes would be harder to read at each of the 14 call sites than the `sprintf()` call it replaced, for a "reuse" that saves one line. `DocumentNumberAllocator` therefore has exactly one job — hand back the next safe integer for a counter — and every call site keeps its own one-line `sprintf()`.

### Decision: Extraction in PHP, not SQL, for the backfill

`BackfillDocumentNumberSequencesCommand` has to parse the sequence integer and the period out of 14 existing text columns using per-source regular expressions (`SEQUENCE_FIRST` flips capture-group order for the two roman-numeral formats). The test suite runs on SQLite (`phpunit.xml`, local/fast) and PostgreSQL (`phpunit.ci.xml`, CI/production-parity); PostgreSQL's `regexp_match` has no SQLite equivalent, and hand-rolling two parallel SQL dialects for a command that runs once, over at most a few thousand rows, reading two columns at a time, is not worth the duplication. The command chunks (`DB::table(...)->chunk(500, ...)`) so memory stays bounded regardless of table size, and reads only `team_id` and the number column per row.

### Decision: Monotonic-with-gaps, not gap-refilling

The old read-max scheme implicitly refilled gaps: delete the highest-numbered document and the next create reissues its number. This change makes numbers strictly monotonic per `(team, key, period)` — the counter only advances, never looks at what currently exists. A rolled-back or deleted create burns its number permanently; a gap in the sequence is possible and expected.

- Alternative considered: keep gap-refilling (continue deriving "next" from existing rows) but fix only the lexical-sort bug. Rejected — refilling is what let a number that had briefly existed on a deleted/superseded document get reissued to a different document later, which is a worse failure mode (silent reuse) than a cosmetic gap. A locked counter that never looks backward at existing data closes both bugs (the race and the reuse) in one mechanism.
- Consequence: a document number written directly into the database (data fix, import, hand correction) is invisible to the counter until `erp:backfill-document-sequences` runs again — the allocator has no way to know a hand-inserted number exists.

### Decision: Buyer invoice numbering moves to issue-time (business decision, not a numbering-mechanism decision)

Independent of the counter-row mechanism, `BuyerInvoice` numbering moved from create-time (`creating()`/factory-time) to issue-time (`markAsSent()`). This is a deliberate product decision riding on the same migration: a draft invoice is disposable in this system (order edits, corrections, discarded drafts are routine before an invoice is sent to a buyer), and consuming a number for every draft would produce visible gaps in the number sequence a buyer actually sees, and worse, would make it possible to reissue a *different* invoice under a number a buyer already associates with a draft they saw. `assignNumberIfMissing()` is idempotent (checks `invoice_number !== null && !== ''` before allocating) so calling `markAsSent()` on an already-sent invoice, or calling `assignNumberIfMissing()` directly, never renumbers. No other document type in this change moved its allocation point — this is scoped to buyer invoices only.

## Risks / Trade-offs

- **Gaps are permanent, by design.** A rolled-back create or a deleted document leaves a hole in the number sequence forever. Mitigated by: this is disclosed behaviour (documented here and in the code), and the alternative (refilling) is the mechanism that caused the reuse bug this change fixes.
- **Hand-inserted numbers are invisible until backfill re-runs.** If an operator ever inserts a document number directly (bypassing the model/observer), the counter does not know about it and could later hand out a colliding number. Mitigated by: the unique index on the number column still exists as a backstop — a collision fails the save rather than silently duplicating — and `erp:backfill-document-sequences --dry-run` can be re-run on demand to detect drift.
- **The first-allocation retry is exactly one retry.** If a third or later concurrent caller also loses the insert race in the same instant, `next()` does not retry again and the exception propagates. This was judged acceptable: the retry exists only to resolve the *very first* allocation for a counter row that has never been created before, an event that happens once per `(team, key, period)` ever, not under sustained load.

## Migration Plan

1. `2026_07_29_110000_create_document_number_sequences_table` — creates the empty counter table.
2. `2026_07_29_130000_make_buyer_invoice_number_nullable` — `buyer_invoices.invoice_number` becomes nullable (down-migration restores `NOT NULL`, which is safe only if no unnumbered draft exists — acceptable for a rollback path since the up-migration also created the parallel counter/backfill).
3. `2026_07_29_140000_backfill_document_number_sequences` — invokes `erp:backfill-document-sequences` from within the migration, so `migrate` on any existing database seeds every counter above its highest issued number before the first post-cutover create can happen. The migration throws (aborting `migrate`) if the command exits non-zero, rather than leaving a silently under-seeded counter. Down-migration is a no-op: there is nothing to restore that isn't derivable again from existing documents, and dropping the seeded counters would hand the next create a number already in use — the exact bug this exists to prevent.
4. No coordinated deploy step beyond running migrations; the command is idempotent and safe to invoke again manually (e.g. after a data import) via `php artisan erp:backfill-document-sequences`.

## Open Questions

- `Shipment::do_number` (Delivery Order PDF number) still uses the old read-max scheme and was not migrated in this change. Whether it should move onto `DocumentNumberAllocator` is an open follow-up, not resolved here.

# Add Document Number Sequences

> **Retroactive documentation.** This proposal describes work that is already implemented, tested, and merged to `master` (commits `892f3c75`, `ba8e1459`, `3ef7500b`, `c6cb05c1`, `3d823001`, `34c82c9a`, and the migrations dated 2026-07-29). No further implementation is required; this change brings the specs in sync with shipped behaviour.

## Why

All 14 document-number generators in the system (`request`, `project`, `buyer_quote`, `buyer_order`, `buyer_invoice`, `buyer_payment`, `supplier_quote`, `supplier_order`, `supplier_invoice`, `supplier_payment`, `shipment`, `acceptance_report`, `quotation_evaluation`, `profit_and_loss`) generated their next number by reading the current maximum and adding one. That scheme had two bugs:

1. **Concurrent creates raced.** Two requests reading the same "current max" at once would both try to save the same number. Every number column carries a unique index, so the race produced a failed save for the loser — a reliability problem, not silent duplication, but a failure a user would hit and not understand.
2. **The "maximum" was found with a string sort** (`orderByDesc('<x>_number')`). Lexical ordering puts `'9999'` above `'10000'`, so once a team passed 10,000 documents of one kind in one year, the generator returned `9999+1 = 10000` — a number already in use — and **every subsequent create in that team/year failed** until manually fixed. Verified empirically against the old query.

Two generators, `QuotationEvaluation` and `ProfitAndLoss`, had a second independent bug: they ordered by `id` and derived the next increment from the last **inserted** row rather than the highest number issued. An out-of-order insert (e.g. a backfilled or re-saved record) could restart the count and reissue a number.

## What Changes

- Added a `document_number_sequences` table: one counter row per (team, document key, calendar-year period), unique on the triple.
- Added `App\Services\Erp\Numbering\DocumentNumberAllocator`, which takes the next integer under `SELECT ... FOR UPDATE` inside a transaction (serialising concurrent creates on the counter row instead of racing a read-max query), retries once if the very first allocation for a new counter row hits a unique-constraint violation (two concurrent first-creates both find no row), and exposes `peek()` (read without advancing) and `seed()` (set the counter, used by backfill and tests).
- Added `App\Console\Commands\BackfillDocumentNumberSequencesCommand` (`erp:backfill-document-sequences`): a one-shot, idempotent command that scans existing documents in PHP (chunked, portable across SQLite and PostgreSQL — the test suite runs on both), extracts the highest sequence number issued per (team, period) for each of the 14 sources, and seeds each counter to one past that number. It never lowers a counter, it counts soft-deleted rows (so their numbers are never reissued), and `--dry-run` reports without writing. A migration (`2026_07_29_140000_backfill_document_number_sequences`) invokes this command during `migrate` so the cutover is guaranteed rather than left to a runbook step.
- All 14 call sites (7 `Model::generateNextNumber()` static methods and 7 model observers' `creating()` hooks) now call `DocumentNumberAllocator::next()` for the integer sequence and keep their own `sprintf()`/format logic locally — every existing document number format (dashed `PREFIX-YYYY-NNNN`, PO suffix letters, QE/PNL roman-month formats, `AR-YYYY-NNNN`) is unchanged.
- **BREAKING:** Buyer invoice numbering moved from create-time to issue-time. A newly created `BuyerInvoice` now carries `invoice_number = NULL`. The number is allocated inside `BuyerInvoice::markAsSent()` via the idempotent `assignNumberIfMissing()` — a discarded draft never consumes a number, and calling `markAsSent()` (or `assignNumberIfMissing()`) again on an already-numbered invoice is a no-op. `buyer_invoices.invoice_number` became nullable (migration `2026_07_29_130000_make_buyer_invoice_number_nullable`); the `(team_id, invoice_number)` unique index is unchanged because PostgreSQL and SQLite both treat NULLs as distinct, so any number of unnumbered drafts coexist.
- **BREAKING:** Numbers are now strictly monotonic per (team, key, period) with no gap-refilling. The old read-max scheme would refill a gap left by a deleted document — and could therefore reissue a number that had briefly existed on a different document. The counter never reuses a number; a rolled-back or deleted create simply leaves a gap. A document number written directly to the database (bypassing the allocator) is invisible to the counter until the backfill command runs again.

## Impact

- Affected specs: `erp-finance`, `erp-orders`, `erp-quoting`, `erp-trading-core`, `erp-shipments`
- Affected code:
  - `app/Services/Erp/Numbering/DocumentNumberAllocator.php` (new)
  - `app/Console/Commands/BackfillDocumentNumberSequencesCommand.php` (new)
  - `database/migrations/2026_07_29_110000_create_document_number_sequences_table.php`, `2026_07_29_130000_make_buyer_invoice_number_nullable.php`, `2026_07_29_140000_backfill_document_number_sequences.php` (new)
  - `app/Models/BuyerQuote.php`, `BuyerOrder.php`, `BuyerInvoice.php`, `BuyerPayment.php`, `AcceptanceReport.php`, `QuotationEvaluation.php`, `ProfitAndLoss.php` (generator methods repointed to the allocator)
  - `app/Observers/SupplierQuoteObserver.php`, `SupplierOrderObserver.php`, `SupplierInvoiceObserver.php`, `SupplierPaymentObserver.php`, `RequestObserver.php`, `ShipmentObserver.php`, `ProjectObserver.php` (generator methods repointed to the allocator)
  - Not affected: `Shipment::do_number` (the Delivery Order PDF number) is a separate, still read-max-based generator not covered by this change.
- No new external dependency. No change to any existing document number's textual format.

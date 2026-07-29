# Tasks

## 1. Counter-row infrastructure
- [x] 1.1 Migration `2026_07_29_110000_create_document_number_sequences_table`: `document_number_sequences` table, unique on `(team_id, key, period)`
- [x] 1.2 `DocumentNumberAllocator::next()` — `SELECT ... FOR UPDATE` inside a transaction, retry once on unique-violation (SQLSTATE 23505 on PostgreSQL, 23000 + "unique" message on SQLite)
- [x] 1.3 `DocumentNumberAllocator::peek()` and `::seed()` for diagnostics/backfill/tests

## 2. Backfill and cutover
- [x] 2.1 `erp:backfill-document-sequences` command with `SOURCES` covering all 14 document keys, chunked PHP scan (portable across SQLite/PostgreSQL), `--dry-run` flag
- [x] 2.2 Command counts soft-deleted rows, never lowers a counter, skips a source table that doesn't exist yet (fresh installs replaying history)
- [x] 2.3 Migration `2026_07_29_140000_backfill_document_number_sequences` invokes the command during `migrate` and fails the migration if the command exits non-zero

## 3. Repoint the 14 call sites
- [x] 3.1 `BuyerQuote::generateNextNumber()`, `BuyerOrder::generateNextNumber()`, `BuyerPayment::generateNextNumber()`
- [x] 3.2 `AcceptanceReport::generateReportNumber()`, `QuotationEvaluation::generateQeNumber()`, `ProfitAndLoss::generatePnlNumber()` — also fixes the id-ordering/last-inserted-row bug
- [x] 3.3 `RequestObserver`, `ProjectObserver`, `ShipmentObserver`, `SupplierQuoteObserver`, `SupplierOrderObserver` (base PO number only; suffix reuse path untouched), `SupplierInvoiceObserver`, `SupplierPaymentObserver`

## 4. Buyer invoice issue-time numbering
- [x] 4.1 Migration `2026_07_29_130000_make_buyer_invoice_number_nullable`
- [x] 4.2 `BuyerInvoice::assignNumberIfMissing()` (idempotent) called from `markAsSent()`
- [x] 4.3 `BuyerInvoice::generateNextNumber()` repointed to the allocator

## 5. Tests
- [x] 5.1 `DocumentNumberAllocatorTest` — fresh sequence starts at 1, advances monotonically, independent per key/period/team, crosses 9999 without regressing, `peek()`/`seed()`
- [x] 5.2 `BackfillDocumentNumberSequencesTest` — seeds above highest existing number, immune to lexical-order-past-9999, counts soft-deleted rows, idempotent/never lowers, dry-run writes nothing, skips missing source table
- [x] 5.3 `SpecialFormatNumberTest` — QE/PNL/acceptance-report formats unchanged; 30 allocations each never collide
- [x] 5.4 `ModelDocumentNumberTest`, `ObserverDocumentNumberTest`, `SupplierOrderNumberTest`, `SupplierQuoteNumberTest` — per-call-site coverage
- [x] 5.5 `InvoiceNumberAtIssueTest` — draft stays unnumbered, many unnumbered drafts coexist, number assigned on `markAsSent()`, discarded draft costs nothing, re-issuing is idempotent, consecutive issued invoices have no gaps
- [x] 5.6 `BackfillDocumentNumberSequencesMigrationTest` — migration-level cutover behaviour

## 6. Quality gates
- [x] 6.1 Pint/Rector clean on all changed files
- [x] 6.2 PHPStan level 7 clean
- [x] 6.3 Full suite green on SQLite (local) and PostgreSQL (CI)

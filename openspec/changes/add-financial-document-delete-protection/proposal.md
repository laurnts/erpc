# Add Financial Document Delete Protection

**Status: retroactive.** This proposal documents work that is already implemented, tested,
and pushed to `main` (migration `2026_07_29_100000_restrict_deletes_on_financial_documents.php`,
test `tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php`). It exists to bring the
spec in line with shipped behaviour, not to seek approval to build it.

## Why

Eight foreign keys ran from financial documents back to the request or company they
document with `ON DELETE CASCADE`. Deleting a `Request` (or, transitively, a `Company`)
therefore destroyed its buyer/supplier orders, invoices, payments, and profit-and-loss
record at the database level — bypassing the models' soft-delete convention entirely.
A request or company that has produced financial records must be archived, never hard-deleted;
the database was not enforcing that.

## What Changes

- Eight foreign keys are converted from `cascadeOnDelete()` to `restrictOnDelete()`:
  - `buyer_orders.request_id` → `requests`, `buyer_orders.buyer_id` → `companies`
  - `buyer_invoices.request_id` → `requests`
  - `buyer_payments.buyer_invoice_id` → `buyer_invoices`
  - `supplier_invoices.request_id` → `requests`, `supplier_invoices.supplier_id` → `companies`
  - `supplier_payments.supplier_invoice_id` → `supplier_invoices`
  - `profit_and_losses.request_id` → `requests`
- Every `team_id` foreign key on these tables is deliberately left as `cascadeOnDelete()`:
  deleting a `Team` is intentional account closure and must still remove everything under
  it (requests, companies, and their financial documents) in one statement. Verified directly
  against PostgreSQL 17, by hand, in a throwaway database mirroring the real constraint shape
  (`teams` ← `requests` `CASCADE`; `buyer_invoices` → `teams` `CASCADE`, → `requests` `RESTRICT`):
  a direct `DELETE FROM requests` is rejected by the RESTRICT foreign key as expected, while
  `DELETE FROM teams` still succeeds and removes the team, its requests, and their financial
  documents. This works because PostgreSQL queues referential-integrity checks as after-statement
  triggers — within one `DELETE FROM teams` statement, the `team_id` CASCADEs remove the
  referencing rows before the RESTRICT triggers on `request_id`/`buyer_invoice_id`/etc. ever fire,
  so those triggers never see an orphan. **This verification was a manual database probe, not an
  automated test** — there is no regression test guarding the team-cascade half of this behaviour
  (see tasks.md item 2.6, unchecked).
- **BREAKING** (data-layer): `Request::forceDelete()` / `Company::forceDelete()` now fail
  with a `QueryException` (foreign key violation) once any financial document exists for
  that request/company, where they previously succeeded and silently cascaded. A request
  or company with no financial documents can still be force-deleted.
- Because PostgreSQL aborts the enclosing transaction after a `RESTRICT` violation, callers
  that need to keep using the connection afterward (e.g. to assert the row survived) must
  run the force-delete inside its own savepoint (`DB::transaction()`), not the caller's
  outer transaction.
- `openspec/project.md` is rewritten to describe this ERP's actual domain (deal-centric
  quote-to-cash / source-to-pay, `Request` as the system of record, buyer/supplier document
  mirrors, margin and P&L) instead of the upstream Relaticle CRM domain (Companies, People,
  Opportunities, Tasks, Notes) it was forked from.

## Impact

- Affected specs: `erp-finance` (buyer/supplier invoice, payment, and P&L delete protection),
  `erp-orders` (buyer order delete protection), `erp-trading-core` (request/company hard-delete
  protection and the team-delete exception)
- Affected code:
  - `database/migrations/2026_07_29_100000_restrict_deletes_on_financial_documents.php` (new)
  - `tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php` (new)
  - `openspec/project.md` (rewritten)
- No application code changed; this is a database constraint change plus documentation.

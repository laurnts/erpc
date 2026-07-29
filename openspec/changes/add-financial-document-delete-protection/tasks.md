# Tasks

## 1. Migration
- [x] 1.1 Write `database/migrations/2026_07_29_100000_restrict_deletes_on_financial_documents.php` dropping and re-adding, with `restrictOnDelete()`, the 8 foreign keys: `buyer_orders.request_id`, `buyer_orders.buyer_id`, `buyer_invoices.request_id`, `buyer_payments.buyer_invoice_id`, `supplier_invoices.request_id`, `supplier_invoices.supplier_id`, `supplier_payments.supplier_invoice_id`, `profit_and_losses.request_id`
- [x] 1.2 Leave every `team_id` foreign key on these tables untouched (still `cascadeOnDelete()`)
- [x] 1.3 `down()` reverses each constraint back to `cascadeOnDelete()`

## 2. Verification
- [x] 2.1 Manually verify on PostgreSQL that deleting a `Team` still succeeds and cascades to its requests, companies, and their financial documents in one statement (RI after-triggers see no orphan)
- [x] 2.2 Feature test `tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php`: force-deleting a request with a buyer invoice throws `QueryException` and the request row survives
- [x] 2.3 Feature test: force-deleting a company whose request has a buyer invoice throws `QueryException` (delete cascades company → request via the untouched `requests.buyer_id` cascade, then is rejected at the restricted `buyer_invoices.request_id` edge)
- [x] 2.4 Feature test: force-deleting a request with no financial documents still succeeds
- [x] 2.5 Confirm the RESTRICT-inside-transaction interaction: since PostgreSQL aborts the enclosing transaction on a FK violation, the blocked force-delete is run inside its own `DB::transaction()` savepoint so subsequent assertions in the same test can still query
- [ ] 2.6 Add an automated regression test for the team-cascade half of this behaviour (a `Team` with a request that has a `BuyerInvoice` can still be deleted, and the team, request, and invoice are all gone afterward). Currently only verified by a manual PostgreSQL 17 probe against a throwaway schema (see proposal.md) — no test in `tests/` exercises `Team::forceDelete()`/`Team::delete()` cascading through a protected financial document

## 3. Documentation
- [x] 3.1 Rewrite `openspec/project.md` to describe this ERP's actual domain (deal-centric quote-to-cash/source-to-pay via `Request`, buyer/supplier document mirrors, margin/P&L) instead of the inherited Relaticle CRM description
- [x] 3.2 Write this retroactive OpenSpec change (`add-financial-document-delete-protection`) documenting the shipped migration and test
- [x] 3.3 `openspec validate add-financial-document-delete-protection --strict` passes

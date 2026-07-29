# erp-finance Delta

## ADDED Requirements

### Requirement: Financial Document Delete Protection
The system SHALL enforce database-level `RESTRICT` (not `CASCADE`) on the foreign keys running
from finance-side documents back to the request or company they document: `buyer_invoices.request_id`,
`buyer_payments.buyer_invoice_id`, `supplier_invoices.request_id`, `supplier_invoices.supplier_id`,
`supplier_payments.supplier_invoice_id`, and `profit_and_losses.request_id`. A request or company
that has produced any of these documents SHALL NOT be hard-deletable; it must be archived
(soft-deleted) instead. Each table's `team_id` foreign key is unaffected and remains
`cascadeOnDelete()`.

#### Scenario: Force-deleting a request with a buyer invoice is rejected
- **WHEN** `Request::withTrashed()->forceDelete()` is called on a request that has a `BuyerInvoice`
- **THEN** the database raises a foreign key violation
- **AND** the request row still exists afterward

#### Scenario: Force-deleting a company is blocked by its request's buyer invoice
- **WHEN** `Company::withTrashed()->forceDelete()` is called on a buyer company whose request has
  a `BuyerInvoice`
- **THEN** the delete cascades from the company to its request (the `requests.buyer_id` foreign
  key is unchanged and still cascades)
- **AND** is then rejected at the request, because `buyer_invoices.request_id` now restricts
- **AND** neither the company nor the request row is removed

#### Scenario: A RESTRICT violation aborts the enclosing transaction
- **WHEN** a force-delete is blocked by one of these RESTRICT constraints while running inside a
  database transaction
- **THEN** PostgreSQL marks that transaction as aborted
- **AND** code that needs to keep querying afterward (e.g. to assert the row survived) must run
  the force-delete inside its own savepoint (`DB::transaction()`), not directly in the caller's
  outer transaction

#### Scenario: A request with no financial documents can still be force-deleted
- **WHEN** a request with no buyer/supplier orders, invoices, payments, or profit-and-loss record
  is force-deleted
- **THEN** the delete succeeds and the row is removed

# Tasks

## 1. Schema and backfill
- [x] 1.1 Add nullable `buyer_orders.credit_reserved_at` timestamp plus
      `buyer_orders_credit_exposure_index` on `(buyer_id, status, credit_reserved_at)`
- [x] 1.2 Backfill `credit_reserved_at` from `buyer_credit_usage_histories` debit
      rows, matching both the FQCN and legacy bare `'buyer_order'` `related_type`
      forms so existing data is not silently skipped
- [x] 1.3 Migration test proves the backfill populates `credit_reserved_at` from
      an FQCN-stamped history row (`tests/Feature/Migrations/AddCreditReservedAtToBuyerOrdersTableTest.php`)

## 2. Mark credit-reserving orders
- [x] 2.1 `BuyerOrder::confirm()` stamps `credit_reserved_at` inside the locked
      transaction only when credit was actually reserved (skipped for
      zero/negative totals, `credit_status` disabled, or `useCredit: false`)
- [x] 2.2 `BuyerOrder::hasReservedCredit()` reads the column instead of probing
      `BuyerCreditUsageHistory`
- [x] 2.3 Tests cover stamped / not-stamped paths for all three skip conditions
      (`tests/Feature/Erp/Credit/CreditReservedAtTest.php`)

## 3. Derive exposure on Company
- [x] 3.1 `Company::creditExposure()` accessor sums `total - credit_released`
      over confirmed, credit-reserved, non-deleted buyer orders
- [x] 3.2 `Company::derivedAvailableCredit()` accessor: `max(0, credit_limit - credit_exposure)`
- [x] 3.3 `Company::withCreditExposure()` scope adds a `credit_exposure` select
      alias for SQL-side sort/filter
- [x] 3.4 `Company::creditExposureSql()` exposes the raw SQL + bindings for
      callers that must inline the subquery into a larger `ORDER BY` expression
      (PostgreSQL rejects referencing a select alias inside an expression)
- [x] 3.5 Tests cover zero orders, single order, partial release, unreserved
      orders excluded, unconfirmed orders excluded, soft-deleted orders
      excluded, multi-order sum, scope parity, and SQL-side sorting
      (`tests/Feature/Erp/Credit/DerivedCreditExposureTest.php`)

## 4. Stop writing the counters
- [x] 4.1 `BuyerOrder::confirm()` / `restoreCredit()` / `reconcileReleasedCreditFor()`
      no longer write `companies.credit_used` or `companies.available_credit`
- [x] 4.2 `BuyerCreditLimitRequest::approve()` reads `derived_available_credit`
      for the audit-row before/after fields instead of a stored value
- [x] 4.3 `getCreditLimitWarning()` / `exceedsCreditLimit()` read `credit_exposure`
      / `derived_available_credit`
- [x] 4.4 Test proves `credit_used` is untouched by `confirm()` while
      `credit_exposure` reflects it
      (`tests/Feature/Erp/Credit/CreditCounterNotWrittenTest.php`)
- [x] 4.5 `BuyerCreditUsageHistory` audit rows still written on debit, credit
      restore, and release-reconciliation

## 5. Reconciliation command
- [x] 5.1 `erp:reconcile-credit-exposure {--tolerance=0.01}` chunks buyers,
      compares stored `credit_used` vs. derived `credit_exposure`, prints a
      `DRIFT` line per buyer outside tolerance, exits non-zero if any drifted
- [x] 5.2 Ran against the development database; found and recorded real drift:
      `StoreFrame Retail (id=31, team=1): stored=0.0000 derived=11200000.0000`
- [x] 5.3 Tests cover agreement (exit 0), disagreement (exit 1, buyer named),
      and sub-cent tolerance (`tests/Feature/Erp/Credit/ReconcileCreditExposureTest.php`)

## 6. Display surfaces
- [x] 6.1 `BuyerResource` (list + view) shows `derived_available_credit`,
      sortable via the inlined `creditExposureSql()` expression
- [x] 6.2 `BuyerCreditLimitOverviewResource` (list + view) shows
      `derived_available_credit`, default-sorted by it

## 7. Remove dead locking
- [x] 7.1 Removed four no-op `$model->lockForUpdate()` calls on `Company` (the
      call forwarded to a discarded query builder via `Model::__call` and
      protected nothing)
- [x] 7.2 Documented, in a code comment on `BuyerOrder::confirm()`, the
      pre-existing cross-order race that remains open: two different orders
      for the same buyer confirming concurrently can both pass the credit
      check because exposure is derived from `companies`, not locked there

## 8. Quality gates
- [x] 8.1 Pint and Rector clean on changed files
- [x] 8.2 Full affected test suite green
      (`tests/Feature/Erp/Credit/*`, `tests/Feature/Migrations/AddCreditReservedAtToBuyerOrdersTableTest.php`,
      `tests/Unit/Models/CompanyCreditLimitTest.php`, credit-limit-approval and
      buyer-resource Filament tests)
- [x] 8.3 `openspec validate refactor-derive-credit-exposure --strict` passes

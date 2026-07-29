# Refactor: Derive Buyer Credit Exposure Instead of Storing It

> **Retroactive documentation.** This change is already implemented, tested, and
> pushed to `master` (commits `973f8c02`..`f354b386`). This proposal records what
> shipped; it is not a plan for future work.

## Why

`companies` carried three columns that had to agree by convention:
`credit_limit`, `credit_used`, `available_credit`. The latter two were
hand-incremented and hand-decremented in `BuyerOrder::confirm()` /
`restoreCredit()` / `reconcileReleasedCreditFor()` using PHP floats, with
`max(0, …)` clamps that turned drift into *invisible* drift — nothing ever
compared the counter against the ledger it was supposed to summarize.

Running the new reconciliation command against the development database
proved the drift was real, not hypothetical:

```
DRIFT  StoreFrame Retail (id=31, team=1): stored=0.0000 derived=11200000.0000 difference=+11200000.0000
1 of 9 buyers drifted.
```

`buyer_credit_usage_histories` recorded an 11,200,000 debit for this buyer;
`credit_used` read `0.00`. The one order in the dataset that ever reserved
credit had already put the stored counter and its own ledger out of sync by
the full amount.

Separately, four `$model->lockForUpdate()` calls (on `Company`) were dead
code: `lockForUpdate()` is a query-builder method, so `Model::__call` forwarded
it to `newQuery()->lockForUpdate()` — a builder that was never executed,
built and discarded — while the following `refresh()` re-read the row without
any lock. They protected nothing.

## What Changes

- Added `buyer_orders.credit_reserved_at` (nullable timestamp), non-null
  exactly when an order debited credit at confirmation. Backfilled from
  `buyer_credit_usage_histories` debit rows on migration.
  `BuyerOrder::hasReservedCredit()` now reads this column instead of probing
  the history table.
- Buyer credit exposure is now **derived**, never stored:
  `SUM(total - credit_released)` over `buyer_orders` where
  `buyer_id = :company AND status = 'confirmed' AND credit_reserved_at IS NOT NULL
  AND deleted_at IS NULL`.
- `Company` gained a `credit_exposure` accessor, a `derived_available_credit`
  accessor (`max(0, credit_limit - credit_exposure)`), and a
  `withCreditExposure()` scope that adds a `credit_exposure` select alias so
  Filament tables (Buyers list, Buyer Credit Limits Overview) sort and filter
  in SQL instead of computing per row.
- Added `erp:reconcile-credit-exposure` (read-only): chunks buyers, compares
  stored `credit_used` against derived `credit_exposure`, reports any buyer
  outside a configurable tolerance (default 0.01), and exits non-zero on
  drift.
- `BuyerCreditUsageHistory` rows are **still written** on confirm, credit
  restore, and release-reconciliation — the audit trail is unchanged; it
  simply stopped being the thing exposure is computed from.
- Removed four no-op `$model->lockForUpdate()` calls that were silently
  discarded builders (see Why).
- **Expand-only.** `companies.credit_used` and `companies.available_credit`
  still exist and are simply no longer written by `BuyerOrder`. Dropping them
  is intentionally deferred to a later change, gated on the reconciliation
  command reporting stable results over time.
- A pre-existing race is now documented (in a code comment on
  `BuyerOrder::confirm()`) rather than hidden by the illusion of a lock: two
  *different* orders for the same buyer confirming concurrently each lock only
  their own row, each compute `derived_available_credit` before the other's
  `credit_reserved_at` stamp commits, and both can pass the credit check. This
  is not a regression — the previous `$buyer->lockForUpdate()` was the same
  no-op described above — and it is not fixed by this change.

## Impact

- Affected specs: `erp-trading-core` (Buyers Entity credit fields — MODIFIED),
  `erp-finance` (new Credit Exposure Reconciliation requirement — ADDED)
- Affected code:
  - `app/Models/Company.php` — `credit_exposure`, `derived_available_credit`,
    `withCreditExposure()`, `creditExposureSql()`
  - `app/Models/BuyerOrder.php` — `confirm()`, `restoreCredit()`,
    `reconcileReleasedCreditFor()`, `hasReservedCredit()`,
    `getCreditLimitWarning()`, `exceedsCreditLimit()`
  - `app/Console/Commands/ReconcileCreditExposureCommand.php` (new)
  - `database/migrations/2026_07_29_120000_add_credit_reserved_at_to_buyer_orders_table.php`
    (new column + FQCN-matched backfill)
  - `app/Models/BuyerCreditLimitRequest.php::approve()` (reads
    `derived_available_credit` instead of a stored value around the limit
    change)
  - `app/Filament/Resources/BuyerResource.php`,
    `BuyerCreditLimitOverviewResource.php` (display/sort on
    `derived_available_credit`)
  - Tests: `tests/Feature/Erp/Credit/CreditCounterNotWrittenTest.php`,
    `CreditReservedAtTest.php`, `DerivedCreditExposureTest.php`,
    `ReconcileCreditExposureTest.php`,
    `tests/Feature/Migrations/AddCreditReservedAtToBuyerOrdersTableTest.php`
- No breaking change to any public column or API: `credit_limit`,
  `credit_used`, and `available_credit` remain on `companies` and readable as
  before; only their write path changed.

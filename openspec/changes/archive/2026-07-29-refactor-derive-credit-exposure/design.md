## Context

Buyer credit exposure gates whether a `BuyerOrder` can be confirmed
(`BuyerOrder::confirm()`) and is displayed on two Filament resources
(`BuyerResource`, `BuyerCreditLimitOverviewResource`). Before this change it
was stored on `companies.credit_used` / `companies.available_credit` and
mutated by hand at three call sites (`confirm()`, `restoreCredit()`,
`reconcileReleasedCreditFor()`), each independently computing a new value with
`max(0, …)` clamping. No code ever compared the stored value against the
ledger (`buyer_credit_usage_histories`) that was supposed to justify it, so
disagreement was silent. This is a data-model change (new column, new
computed-vs-stored invariant) with a deliberate migration strategy, so it
warrants recording the decisions rather than leaving them implicit in the
diff.

## Goals / Non-Goals

- Goals:
  - Make credit exposure a single source of truth, computed from the same
    rows an auditor would look at (`buyer_orders` joined against their own
    `credit_released`), not a counter that can drift from them.
  - Make drift detectable (`erp:reconcile-credit-exposure`) before it is made
    impossible (dropping the columns).
  - Preserve the audit trail (`BuyerCreditUsageHistory`) unchanged — it is
    still useful as a human-readable transaction log even though nothing
    reads it to compute current exposure anymore.
  - Remove locking code that gave a false sense of safety
    (`$model->lockForUpdate()` on `Company`, a no-op).
- Non-Goals:
  - Fixing the cross-order concurrent-confirmation race (see Risks below).
    Closing it requires deciding what to lock (the buyer's `companies` row,
    most likely) and that is a separate design decision.
  - Dropping `companies.credit_used` / `companies.available_credit`. That is
    explicitly deferred (see Migration Plan).

## Decisions

- **Derive from `buyer_orders`, not from `buyer_credit_usage_histories`.**
  The ledger records transactions over time; it is the right shape for an
  audit trail but the wrong shape to answer "what is outstanding right now"
  without summing an unbounded, ever-growing table indefinitely. The exposure
  formula reads the current state of each order instead:
  `SUM(total - credit_released)` over confirmed, credit-reserved,
  non-deleted orders for the buyer. This is `O(open orders)`, not
  `O(all-time transactions)`.

- **A new column (`credit_reserved_at`), not a re-derivation of "did this
  order ever reserve credit."** The three conditions under which
  `confirm()` skips reserving credit — non-positive total, `credit_status`
  disabled on the buyer, `useCredit: false` — are not otherwise recoverable
  from the order row after the fact. Before this change,
  `hasReservedCredit()` had to run an `EXISTS` query against
  `buyer_credit_usage_histories`; that cannot be pulled inside an aggregate
  `SUM` over `buyer_orders` without a correlated subquery per row. Stamping
  the fact on the order itself makes it a plain `WHERE` predicate, cheap
  enough to run per row in a Filament table scope.

  **This predicate is load-bearing.** A confirmed order placed while the
  buyer had `credit_status` disabled — or with `useCredit: false` — takes an
  early return in `confirm()` and never debits. Without
  `credit_reserved_at IS NOT NULL` in the aggregate, the query would sum
  `total - credit_released` over *every* confirmed order regardless of
  whether it ever touched credit, inventing exposure out of ordinary orders.
  This is exactly what the reconciliation command caught in the development
  database: the drifted buyer's stored `credit_used` was `0.00`, and the one
  order that legitimately reserved credit accounts for the entire
  11,200,000 derived figure — the filter is what keeps that number from
  being contaminated by the buyer's other, non-reserving orders.

- **A `withCreditExposure()` select-alias scope, plus a separate
  `creditExposureSql()` escape hatch.** Filament list/table sorting needs
  exposure available in SQL, not computed per row in PHP after the query
  runs (`BuyerResource`, `BuyerCreditLimitOverviewResource`). A select alias
  (`addSelect(['credit_exposure' => …])`) covers `ORDER BY credit_exposure`
  directly. It does not cover the other call site, which sorts by *derived
  available credit* — `credit_limit - credit_exposure` — because PostgreSQL
  only allows a bare select alias in `ORDER BY`, not inside a larger
  expression (`ORDER BY credit_limit - credit_exposure` fails with "column
  does not exist"; SQLite allows it, which is why this was not caught until
  the suite ran against PostgreSQL). `creditExposureSql()` returns the raw
  SQL text and bindings so that call site can inline the subquery directly
  into its `orderByRaw()` expression instead of referencing the alias.

- **Expand-only.** `credit_limit`, `credit_used`, and `available_credit` all
  remain fillable, cast, and readable on `Company`. Nothing was dropped.
  `credit_used` is simply never written by `BuyerOrder` anymore.

- **Remove the no-op locks rather than make them real.** The previous
  `$buyer->lockForUpdate()` calls compiled and ran without error — that is
  precisely what made them dangerous. `lockForUpdate()` is defined on the
  query builder, not on `Model`; `Model::__call` forwards unknown instance
  method calls to `$this->newQuery()`, so the call built a fresh, unscoped
  query, applied `FOR UPDATE` to it, and then the query was discarded because
  nothing executed it — the `$buyer` instance already in memory was never
  re-fetched under lock. The following `$buyer->refresh()` (or equivalent
  re-read) ran without any lock held. Deleting the calls changes nothing
  observable; keeping them would have continued to imply a safety property
  that was never real.

## Risks / Trade-offs

- **Unfixed race: concurrent confirmations of *different* orders for the
  same buyer.** `confirm()` locks the *order* row (`SELECT … FOR UPDATE`)
  before stamping `credit_reserved_at`, which correctly serializes two
  attempts to confirm the *same* order. It does not lock the *buyer's*
  `companies` row. Two different orders for the same buyer, confirmed
  concurrently, each:
  1. read `derived_available_credit` (computed from the buyer's
     *currently committed* orders — the other transaction's stamp is not
     yet committed),
  2. see enough headroom and pass the check,
  3. commit their own `credit_reserved_at` stamp.

  Both succeed; the sum of the two orders can exceed `credit_limit`. This
  predates this change — the previous `$buyer->lockForUpdate()` was the
  no-op described above, so it never actually prevented this either — and it
  is not fixed here. It is now recorded in a comment on
  `BuyerOrder::confirm()` instead of being implied-but-absent. Closing it
  would mean locking the buyer's `companies` row for the duration of the
  credit check + stamp, which serializes all confirmations for a buyer and
  is a deliberate throughput trade-off left for a future change.

- **Read-time cost.** Every `derived_available_credit` / `credit_exposure`
  read not covered by `withCreditExposure()` runs an aggregate query over
  `buyer_orders` instead of reading a column. The
  `(buyer_id, status, credit_reserved_at)` index keeps this to an index
  range scan per buyer; this has not been load-tested at scale beyond the
  existing Filament list/detail views.

## Migration Plan

1. **Additive migration** (`2026_07_29_120000_add_credit_reserved_at_to_buyer_orders_table.php`):
   add the nullable column and index, backfill from ledger debit rows. No
   write path changes yet at this point in the sequence conceptually, though
   in practice it shipped alongside the accessor changes in the same change.
2. **Cut over reads**: `Company::creditExposure()` /
   `derivedAvailableCredit()` become the source of truth for all internal
   call sites (`confirm()`, `getCreditLimitWarning()`, Filament resources).
   `credit_used` / `available_credit` remain in place, untouched by these
   reads.
3. **Stop writing the counters**: `BuyerOrder` and
   `BuyerCreditLimitRequest::approve()` no longer write
   `credit_used` / `available_credit`.
4. **Prove parity**: `erp:reconcile-credit-exposure` run ad hoc (and
   available for a scheduled/cron run) compares the now-frozen stored values
   against the derived ones. Any buyer already out of sync at cutover
   surfaces immediately, as it did in development
   (`StoreFrame Retail`, id=31).
5. **(Future, separate change, not part of this one)**: once the
   reconciliation command has reported stable agreement over an operating
   period, drop `companies.credit_used` and `companies.available_credit` in
   a follow-up migration and remove the reconciliation command or repurpose
   it as a standing invariant check with nothing to compare against.

Rollback: the migration's `down()` drops the index and the
`credit_reserved_at` column. Reverting the accessor/write-path changes
would require restoring the old hand-mutation code at the three call sites;
no data is destroyed by rolling back since `credit_used` /
`available_credit` were never removed.

## Open Questions

- What operating period / signal should gate dropping the two legacy
  columns? Not decided as part of this change.
- Should `erp:reconcile-credit-exposure` run on a schedule (Horizon /
  scheduler) rather than ad hoc? Not decided; it exists today as a manually
  invoked Artisan command.
- Should the cross-order race be closed with a `companies` row lock, an
  advisory lock, or a serializable transaction? Not decided; left open in
  `BuyerOrder::confirm()`'s code comment.

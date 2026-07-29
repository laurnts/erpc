# Derived Credit Exposure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop maintaining buyer credit exposure as three hand-mutated float columns and derive it in SQL from the orders that create it, removing an entire class of silent drift — including four `lockForUpdate()` calls that never lock anything.

**Architecture:** `companies.credit_used` and `companies.available_credit` are today
mutable running counters, incremented and decremented in PHP floats with `max(0, …)`
clamps that turn drift into invisible drift. The quantity they approximate is already
exactly derivable: a confirmed buyer order's outstanding exposure is
`total - credit_released`, and `credit_released` is itself maintained as
`min(invoice.amount_paid, total)`. This plan makes exposure a SQL sum over
`buyer_orders`, exposed both as an accessor and as a query scope (so Filament can still
sort and filter on it), keeps `BuyerCreditUsageHistory` as an audit trail rather than a
load-bearing ledger, and follows expand/contract discipline: the columns stop being
written and are verified against the derived value in production before a later change
drops them.

This is §5 and §20.3 of `/Users/laurnts/Sites/pos/ARCHITECTURE.md` applied to credit
instead of stock: *the quantity is computed, never a decremented counter.*

**Tech Stack:** Laravel 12, PostgreSQL 15+, Filament 5, Pest 4.

## Global Constraints

- All PHP files declare `declare(strict_types=1);`
- All classes `final` by default; services `final readonly`
- Comparisons use `===` / `!==` exclusively
- Tooling runs through the Docker wrapper: `php vendor/bin/<tool>`
- Before finalizing any change: `php vendor/bin/rector process <changed files>` then `php vendor/bin/pint --dirty`
- **Expand-only during this plan.** No column is dropped here. `credit_used` and
  `available_credit` remain in the schema, stop being written, and are dropped by a
  separate contract migration only after the reconciliation command from Task 3 reports
  zero drift on production for a full billing cycle.

## The exposure definition

A buyer's outstanding credit exposure is:

```
SUM(buyer_orders.total - buyer_orders.credit_released)
WHERE buyer_id = :company
  AND status = 'confirmed'
  AND credit_reserved_at IS NOT NULL
  AND deleted_at IS NULL
```

Read against today's code this is not a new rule, it is the invariant the existing
mutations were trying to maintain:

- `BuyerOrder::confirm()` (`app/Models/BuyerOrder.php:274`) adds `total` on confirm
- `BuyerOrder::restoreCredit()` (`:401`) sets `credit_released = total`, zeroing exposure
- `BuyerOrder::reconcileReleasedCreditFor()` (`:466`) moves `credit_released` toward
  `min(amount_paid, total)` as payments land and reverse

`credit_reserved_at` is new (Task 1) and replaces the
`BuyerCreditUsageHistory`-EXISTS check in `BuyerOrder::hasReservedCredit()` (`:456`),
which cannot participate in an aggregate query cheaply.

**The `credit_reserved_at` filter is not optional.** Run against the development
database on 2026-07-29, the exposure query *without* it returned 11,200,000 for one
buyer whose orders never reserved credit — confirmed orders placed while credit was
disabled take an early return in `confirm()` and never debit. Dropping that condition
would invent exposure out of ordinary orders.

## Evidence that the counter already drifts

This is not a theoretical risk. On the development database, 2026-07-29:

| | value |
|---|---|
| `buyer_credit_usage_histories` debit for order 9 | `amount = 11,200,000`, `credit_used_after = 11,200,000` |
| `companies.credit_used` for that order's buyer (id 31) | **`0.00`** |
| `companies.credit_limit` / `available_credit` for id 31 | `0.00` / `0.00` |

The ledger records the debit; the counter does not reflect it. One order in the dataset
ever reserved credit, and the counter and the ledger already disagree by the full amount.

**Caveat, stated honestly:** this is a development database, so the divergence could be
seeding or manual editing rather than a live code path — a buyer whose `credit_limit` is
now `0.00` could not have passed `confirm()`'s check at 11.2M, so something reset the
columns after the fact. That is exactly the point. Whether the cause is code or a human
with an admin form, three columns that must agree by convention will drift, and nothing
in the system notices. A derived value cannot be reset out from under its own ledger.

Expect `erp:reconcile-credit-exposure` (Task 3) to report drift on its first run. That
is the command doing its job, not a sign the migration went wrong.

## Known pre-existing defect this plan removes

`$buyer->lockForUpdate();` at `app/Models/BuyerOrder.php:279`, `:413`, `:497` and
`app/Models/BuyerCreditLimitRequest.php:186` does not lock anything. `lockForUpdate()`
is a query-builder method; `Model::__call` forwards it to `newQuery()->lockForUpdate()`,
which returns an unexecuted builder that is then discarded. The `refresh()` that follows
re-reads without a lock. Two concurrent confirmations can therefore both pass the credit
check and both write, losing one debit. Task 4 removes three of these calls entirely
(there is no longer a counter to protect) and replaces the fourth with a real lock.

---

### Task 1: Mark which orders reserved credit

**Files:**
- Create: `database/migrations/2026_07_29_120000_add_credit_reserved_at_to_buyer_orders_table.php`
- Modify: `app/Models/BuyerOrder.php` — `$casts` array, `confirm()`, `hasReservedCredit()`
- Test: `tests/Feature/Erp/Credit/CreditReservedAtTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `buyer_orders.credit_reserved_at` (nullable timestamp), cast to
  `immutable_datetime`. Non-null exactly when the order debited buyer credit at
  confirmation. `BuyerOrder::hasReservedCredit(): bool` keeps its signature but reads
  the column instead of querying `BuyerCreditUsageHistory`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Credit/CreditReservedAtTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_status' => true,
        'credit_limit' => 100000,
    ]);
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

function confirmedOrder(float $total, bool $useCredit = true): BuyerOrder
{
    $order = BuyerOrder::factory()
        ->recycle(test()->team)
        ->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')
        ->for(test()->request)
        ->create(['status' => OrderStatus::DRAFT, 'total' => $total]);

    $order->confirm($useCredit);

    return $order->refresh();
}

it('stamps credit_reserved_at when an order reserves credit', function (): void {
    $order = confirmedOrder(5000);

    expect($order->credit_reserved_at)->not->toBeNull();
});

it('leaves credit_reserved_at null when credit was not used', function (): void {
    $order = confirmedOrder(5000, useCredit: false);

    expect($order->credit_reserved_at)->toBeNull();
});

it('leaves credit_reserved_at null when the buyer has credit disabled', function (): void {
    $this->buyer->update(['credit_status' => false]);

    $order = confirmedOrder(5000);

    expect($order->credit_reserved_at)->toBeNull();
});

it('reports hasReservedCredit from the column', function (): void {
    $reserved = confirmedOrder(5000);
    $unreserved = confirmedOrder(5000, useCredit: false);

    expect($reserved->hasReservedCredit())->toBeTrue()
        ->and($unreserved->hasReservedCredit())->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Credit/CreditReservedAtTest.php`
Expected: FAIL — `Undefined property: App\Models\BuyerOrder::$credit_reserved_at` or a
column-not-found error.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_29_120000_add_credit_reserved_at_to_buyer_orders_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records whether an order debited buyer credit at confirmation. Previously this
 * was inferred by an EXISTS over buyer_credit_usage_histories, which cannot take
 * part in an aggregate exposure query cheaply.
 *
 * The backfill reproduces that same EXISTS once, so existing orders keep their
 * current classification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->timestamp('credit_reserved_at')->nullable()->after('credit_released');
            $table->index(['buyer_id', 'status', 'credit_reserved_at'], 'buyer_orders_credit_exposure_index');
        });

        DB::statement(<<<'SQL'
            UPDATE buyer_orders
            SET credit_reserved_at = COALESCE(confirmed_at, created_at)
            WHERE EXISTS (
                SELECT 1
                FROM buyer_credit_usage_histories h
                WHERE h.related_type = 'buyer_order'
                  AND h.related_id = buyer_orders.id
                  AND h.transaction_type = 'debit'
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->dropIndex('buyer_orders_credit_exposure_index');
            $table->dropColumn('credit_reserved_at');
        });
    }
};
```

Two details in this statement were verified against the running database on 2026-07-29,
because getting either wrong makes the backfill silently classify every historical order
as "did not reserve credit", which zeroes exposure everywhere:

- The table is `buyer_credit_usage_histories` (**plural**), not
  `buyer_credit_usage_history`. The migration filename is singular; the table it creates
  is not.
- `related_type` holds the morph-map alias `'buyer_order'`, **not** the FQCN
  `App\Models\BuyerOrder`.

Re-confirm both before running, in case a morph map entry has changed since:

```bash
docker exec -i postgres-erpc psql -U root -d postgres \
  -c "SELECT DISTINCT related_type FROM buyer_credit_usage_histories;"
```

- [ ] **Step 4: Add the cast**

In `app/Models/BuyerOrder.php`, add to the `casts()` array:

```php
            'credit_reserved_at' => 'immutable_datetime',
```

- [ ] **Step 5: Stamp the column in `confirm()`**

In `app/Models/BuyerOrder.php`, inside the `DB::transaction` closure of `confirm()`,
replace:

```php
            // Update order status
            $this->status = OrderStatus::CONFIRMED;
            $this->confirmed_at = now();
            $this->save();
```

with:

```php
            // Update order status. credit_reserved_at is what makes this order
            // count toward the buyer's exposure; only the credit path sets it.
            $this->status = OrderStatus::CONFIRMED;
            $this->confirmed_at = now();
            $this->credit_reserved_at = now();
            $this->save();
```

The two early-return branches above it (zero total, and `credit_status` false or
`$useCredit` false) already skip this block, so they correctly leave the column null.

- [ ] **Step 6: Rewrite `hasReservedCredit()`**

Replace the whole method in `app/Models/BuyerOrder.php`:

```php
    /**
     * Whether this order reserved buyer credit at confirmation.
     *
     * Reads the credit_reserved_at column rather than probing
     * BuyerCreditUsageHistory, so the same condition can be used inside the
     * aggregate exposure query on Company.
     */
    public function hasReservedCredit(): bool
    {
        return $this->credit_reserved_at !== null;
    }
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Credit/CreditReservedAtTest.php`
Expected: all four tests PASS.

- [ ] **Step 8: Run the existing credit tests**

Run: `php vendor/bin/pest --filter=Credit`
Expected: PASS. Anything failing here is testing the old history-EXISTS behaviour —
read it before changing it.

- [ ] **Step 9: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Models/BuyerOrder.php database/migrations/2026_07_29_120000_add_credit_reserved_at_to_buyer_orders_table.php tests/Feature/Erp/Credit
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Models/BuyerOrder.php database/migrations/2026_07_29_120000_add_credit_reserved_at_to_buyer_orders_table.php tests/Feature/Erp/Credit
git commit -m "feat: mark credit-reserving buyer orders with credit_reserved_at"
```

---

### Task 2: Derive exposure on Company

**Files:**
- Modify: `app/Models/Company.php` — add `buyerOrders()` relation, `creditExposure()`
  accessor, `withCreditExposure()` scope, `availableCredit()` accessor
- Test: `tests/Feature/Erp/Credit/DerivedCreditExposureTest.php`

**Interfaces:**
- Consumes: `buyer_orders.credit_reserved_at`, `buyer_orders.credit_released`
- Produces, on `App\Models\Company`:
  ```php
  public function buyerOrders(): HasMany;              // BuyerOrder, foreign key buyer_id
  protected function creditExposure(): Attribute;      // float, read-only
  protected function derivedAvailableCredit(): Attribute; // float, read-only
  public function scopeWithCreditExposure(Builder $query): Builder;
  ```
  `credit_exposure` is the derived replacement for the `credit_used` column.
  `derived_available_credit` is `max(0, credit_limit - credit_exposure)`.
  `withCreditExposure()` adds a `credit_exposure` select alias so Filament tables can
  sort and filter on it in SQL. Both accessors prefer the aliased value when the scope
  was applied, so a listing page does not fire one subquery per row.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Credit/DerivedCreditExposureTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_status' => true,
        'credit_limit' => 100000,
    ]);
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

function orderWith(array $attributes): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle(test()->team)
        ->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')
        ->for(test()->request)
        ->create($attributes);
}

it('reports zero exposure with no orders', function (): void {
    expect($this->buyer->credit_exposure)->toBe(0.0);
});

it('counts a confirmed reserving order at its unreleased amount', function (): void {
    orderWith([
        'status' => OrderStatus::CONFIRMED,
        'total' => 5000,
        'credit_released' => 0,
        'credit_reserved_at' => now(),
    ]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(5000.0);
});

it('nets off partially released credit', function (): void {
    orderWith([
        'status' => OrderStatus::CONFIRMED,
        'total' => 5000,
        'credit_released' => 2000,
        'credit_reserved_at' => now(),
    ]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(3000.0);
});

it('ignores orders that never reserved credit', function (): void {
    orderWith([
        'status' => OrderStatus::CONFIRMED,
        'total' => 5000,
        'credit_released' => 0,
        'credit_reserved_at' => null,
    ]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(0.0);
});

it('ignores orders that are not confirmed', function (): void {
    orderWith([
        'status' => OrderStatus::DRAFT,
        'total' => 5000,
        'credit_released' => 0,
        'credit_reserved_at' => now(),
    ]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(0.0);
});

it('ignores soft-deleted orders', function (): void {
    $order = orderWith([
        'status' => OrderStatus::CONFIRMED,
        'total' => 5000,
        'credit_released' => 0,
        'credit_reserved_at' => now(),
    ]);
    $order->delete();

    expect($this->buyer->fresh()->credit_exposure)->toBe(0.0);
});

it('sums several orders', function (): void {
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 5000, 'credit_released' => 0, 'credit_reserved_at' => now()]);
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 2500, 'credit_released' => 500, 'credit_reserved_at' => now()]);

    expect($this->buyer->fresh()->credit_exposure)->toBe(7000.0);
});

it('derives available credit from the limit', function (): void {
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 30000, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    expect($this->buyer->fresh()->derived_available_credit)->toBe(70000.0);
});

it('never reports negative available credit', function (): void {
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 150000, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    expect($this->buyer->fresh()->derived_available_credit)->toBe(0.0);
});

it('exposes the same value through the query scope', function (): void {
    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 4200, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    $row = Company::withCreditExposure()->whereKey($this->buyer->getKey())->sole();

    expect((float) $row->credit_exposure)->toBe(4200.0);
});

it('sorts by exposure through the scope', function (): void {
    $other = Company::factory()->buyer()->recycle($this->team)->create(['credit_limit' => 100000]);
    $otherRequest = Request::factory()->recycle($this->team)->recycle($other)->create();

    orderWith(['status' => OrderStatus::CONFIRMED, 'total' => 100, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    BuyerOrder::factory()
        ->recycle($this->team)->recycle($this->currency)
        ->for($other, 'buyer')->for($otherRequest)
        ->create(['status' => OrderStatus::CONFIRMED, 'total' => 900, 'credit_released' => 0, 'credit_reserved_at' => now()]);

    $ordered = Company::withCreditExposure()
        ->whereIn('id', [$this->buyer->getKey(), $other->getKey()])
        ->orderByDesc('credit_exposure')
        ->pluck('id')
        ->all();

    expect($ordered[0])->toBe($other->getKey());
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Credit/DerivedCreditExposureTest.php`
Expected: FAIL — `Undefined property: App\Models\Company::$credit_exposure`.

- [ ] **Step 3: Add the relation and derivation to `Company`**

In `app/Models/Company.php`, add these members. Place `buyerOrders()` next to the
existing `creditUsageHistory()` relation at line 291, and the accessors with the
model's other `Attribute` methods.

```php
    /**
     * Buyer orders placed by this company.
     *
     * @return HasMany<BuyerOrder, $this>
     */
    public function buyerOrders(): HasMany
    {
        return $this->hasMany(BuyerOrder::class, 'buyer_id');
    }

    /**
     * Outstanding buyer credit exposure.
     *
     * Derived, never stored: a confirmed order that reserved credit contributes
     * whatever it has not yet released, and credit_released is maintained as
     * min(invoice.amount_paid, total). The previous credit_used column was a
     * hand-mutated running counter and could drift from this value without any
     * signal.
     *
     * Prefers a credit_exposure select alias when scopeWithCreditExposure() was
     * applied, so listing pages issue one query rather than one per row.
     *
     * @return Attribute<float, never>
     */
    protected function creditExposure(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): float {
                if ($value !== null) {
                    return (float) $value;
                }

                return (float) $this->buyerOrders()
                    ->where('status', OrderStatus::CONFIRMED)
                    ->whereNotNull('credit_reserved_at')
                    ->sum(DB::raw('total - credit_released'));
            },
        );
    }

    /**
     * Credit still available to this buyer: limit minus outstanding exposure.
     *
     * @return Attribute<float, never>
     */
    protected function derivedAvailableCredit(): Attribute
    {
        return Attribute::make(
            get: fn (): float => max(0.0, (float) $this->credit_limit - $this->credit_exposure),
        );
    }

    /**
     * Adds a credit_exposure select alias so tables can sort and filter on
     * exposure in SQL instead of in PHP.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithCreditExposure(Builder $query): Builder
    {
        return $query->addSelect([
            'credit_exposure' => BuyerOrder::query()
                ->selectRaw('COALESCE(SUM(total - credit_released), 0)')
                ->whereColumn('buyer_id', 'companies.id')
                ->where('status', OrderStatus::CONFIRMED)
                ->whereNotNull('credit_reserved_at'),
        ]);
    }
```

Add whichever of these imports the file does not already have:

```php
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
```

Both query paths inherit `BuyerOrder`'s `SoftDeletes` global scope, which is what makes
the "ignores soft-deleted orders" test pass without an explicit `whereNull`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Credit/DerivedCreditExposureTest.php`
Expected: all eleven tests PASS.

If "exposes the same value through the query scope" fails with an ambiguous-column
error, the `addSelect` collided with a bare `select('*')` elsewhere in the chain —
qualify the scope's outer columns with `companies.*` in the failing caller.

- [ ] **Step 5: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Models/Company.php tests/Feature/Erp/Credit/DerivedCreditExposureTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Models/Company.php tests/Feature/Erp/Credit/DerivedCreditExposureTest.php
git commit -m "feat: derive buyer credit exposure from confirmed orders instead of a stored counter"
```

---

### Task 3: Reconciliation command

Before the stored counters stop being written, prove the derived value agrees with them
on real data. This command is also the permanent invariant check afterwards — the
§9.3 discipline of the reference architecture: *the property is asserted continuously,
not once at migration time.*

**Files:**
- Create: `app/Console/Commands/ReconcileCreditExposure.php`
- Test: `tests/Feature/Erp/Credit/ReconcileCreditExposureTest.php`

**Interfaces:**
- Consumes: `Company::withCreditExposure()`, `companies.credit_used`
- Produces: artisan command `erp:reconcile-credit-exposure`, exit code `0` when every
  buyer's stored `credit_used` matches derived exposure within one cent, `1` otherwise.
  Read-only — it never writes.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Credit/ReconcileCreditExposureTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_status' => true,
        'credit_limit' => 100000,
        'credit_used' => 0,
    ]);
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
});

function exposureOrder(float $total, float $released = 0): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle(test()->team)->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')->for(test()->request)
        ->create([
            'status' => OrderStatus::CONFIRMED,
            'total' => $total,
            'credit_released' => $released,
            'credit_reserved_at' => now(),
        ]);
}

it('succeeds when stored and derived agree', function (): void {
    exposureOrder(5000);
    $this->buyer->update(['credit_used' => 5000]);

    $this->artisan('erp:reconcile-credit-exposure')->assertExitCode(0);
});

it('fails and names the buyer when they disagree', function (): void {
    exposureOrder(5000);
    $this->buyer->update(['credit_used' => 4200]);

    $this->artisan('erp:reconcile-credit-exposure')
        ->expectsOutputToContain($this->buyer->name)
        ->assertExitCode(1);
});

it('tolerates sub-cent differences', function (): void {
    exposureOrder(5000);
    $this->buyer->update(['credit_used' => 5000.004]);

    $this->artisan('erp:reconcile-credit-exposure')->assertExitCode(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Credit/ReconcileCreditExposureTest.php`
Expected: FAIL — `The command "erp:reconcile-credit-exposure" does not exist.`

- [ ] **Step 3: Write the command**

Create `app/Console/Commands/ReconcileCreditExposure.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Compares the stored credit_used counter against exposure derived from
 * confirmed buyer orders.
 *
 * Runs read-only. While both exist it proves the cutover is safe; once the
 * column is dropped it becomes a standing invariant check — the derived value
 * is compared against nothing and the command reports only totals, which is the
 * point at which it should be retired or repurposed.
 */
final class ReconcileCreditExposure extends Command
{
    protected $signature = 'erp:reconcile-credit-exposure {--tolerance=0.01 : Absolute difference treated as agreement}';

    protected $description = 'Report buyers whose stored credit_used disagrees with derived exposure';

    public function handle(): int
    {
        $tolerance = (float) $this->option('tolerance');
        $drifted = 0;
        $checked = 0;

        Company::query()
            ->where('is_buyer', true)
            ->withCreditExposure()
            ->chunkById(200, function ($companies) use ($tolerance, &$drifted, &$checked): void {
                foreach ($companies as $company) {
                    $checked++;
                    $stored = (float) $company->credit_used;
                    $derived = (float) $company->credit_exposure;
                    $difference = round($stored - $derived, 4);

                    if (abs($difference) <= $tolerance) {
                        continue;
                    }

                    $drifted++;
                    $this->line(sprintf(
                        'DRIFT  %s (id=%d, team=%d): stored=%.4f derived=%.4f difference=%+.4f',
                        $company->name, $company->getKey(), $company->team_id, $stored, $derived, $difference,
                    ));
                }
            });

        if ($drifted === 0) {
            $this->info(sprintf('%d buyers checked, no drift.', $checked));

            return self::SUCCESS;
        }

        $this->error(sprintf('%d of %d buyers drifted.', $drifted, $checked));

        return self::FAILURE;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Credit/ReconcileCreditExposureTest.php`
Expected: all three tests PASS.

If `chunkById` errors with an ambiguous `id`, the `addSelect` from
`withCreditExposure()` has shadowed the primary key — pass the qualified column:
`chunkById(200, $callback, 'companies.id', 'id')`.

- [ ] **Step 5: Run against a production database copy**

```bash
php artisan erp:reconcile-credit-exposure
```
Expected on a healthy dataset: `N buyers checked, no drift.`

**Any drift found here is the point of this whole plan.** Each drifted buyer is a case
where the counter and reality already diverged. Investigate before continuing to Task 4
— the derived value is the correct one, but a large difference may indicate an exposure
rule this plan's definition does not capture (for example credit consumed by something
other than a confirmed buyer order). If such a rule exists, extend the definition in
Task 2 and re-run, rather than adjusting the tolerance.

- [ ] **Step 6: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Console/Commands/ReconcileCreditExposure.php tests/Feature/Erp/Credit/ReconcileCreditExposureTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Console/Commands/ReconcileCreditExposure.php tests/Feature/Erp/Credit/ReconcileCreditExposureTest.php
git commit -m "feat: add erp:reconcile-credit-exposure to prove stored and derived credit agree"
```

---

### Task 4: Stop writing the counters; remove the fake locks

**Files:**
- Modify: `app/Models/BuyerOrder.php` — `confirm()`, `restoreCredit()`,
  `reconcileReleasedCreditFor()`, `exceedsCreditLimit()`
- Modify: `app/Models/BuyerCreditLimitRequest.php:186`
- Modify: `app/Services/Erp/CreditLimitWarningService.php`
- Test: `tests/Feature/Erp/Credit/CreditCounterNotWrittenTest.php`

**Interfaces:**
- Consumes: `Company::$credit_exposure`, `Company::$derived_available_credit`
- Produces: no signature changes. `companies.credit_used` and
  `companies.available_credit` are no longer written by any code path.
  `BuyerCreditUsageHistory` rows are still written — they remain the human-readable
  audit trail, they just stop being load-bearing.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Credit/CreditCounterNotWrittenTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerCreditUsageHistory;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_status' => true,
        'credit_limit' => 100000,
        'credit_used' => 0,
        'available_credit' => 100000,
    ]);
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
});

function draftOrder(float $total): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle(test()->team)->recycle(test()->currency)
        ->for(test()->buyer, 'buyer')->for(test()->request)
        ->create(['status' => OrderStatus::DRAFT, 'total' => $total]);
}

it('does not touch the credit_used column on confirm', function (): void {
    draftOrder(5000)->confirm();

    expect((float) $this->buyer->fresh()->credit_used)->toBe(0.0);
});

it('reflects the confirmation in derived exposure instead', function (): void {
    draftOrder(5000)->confirm();

    expect($this->buyer->fresh()->credit_exposure)->toBe(5000.0);
});

it('still writes an audit trail row on confirm', function (): void {
    $order = draftOrder(5000);
    $order->confirm();

    expect(BuyerCreditUsageHistory::query()
        ->where('related_id', $order->getKey())
        ->where('transaction_type', 'debit')
        ->exists())->toBeTrue();
});

it('drops exposure back to zero when credit is restored', function (): void {
    $order = draftOrder(5000);
    $order->confirm();
    $order->refresh()->restoreCredit();

    expect($this->buyer->fresh()->credit_exposure)->toBe(0.0);
});

it('refuses an order that exceeds derived available credit', function (): void {
    draftOrder(90000)->confirm();

    expect(fn (): mixed => draftOrder(20000)->confirm())
        ->toThrow(InvalidArgumentException::class, 'Insufficient credit');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Credit/CreditCounterNotWrittenTest.php`
Expected: "does not touch the credit_used column on confirm" FAILS with `5000.0`.

- [ ] **Step 3: Rewrite `confirm()`**

In `app/Models/BuyerOrder.php`, replace the whole `DB::transaction` block in `confirm()`
with:

```php
        // The buyer row is no longer mutated, so there is no counter to protect
        // and no lock to take. The order row is locked because credit_reserved_at
        // is what makes this order count toward exposure — two concurrent
        // confirmations of the same order must not both stamp it.
        \Illuminate\Support\Facades\DB::transaction(function () use ($orderTotal, $buyer): void {
            $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->credit_reserved_at !== null) {
                return;
            }

            $currentAvailableCredit = $buyer->derived_available_credit;

            if ($currentAvailableCredit < $orderTotal) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Insufficient credit. Available: %s, Required: %s',
                        number_format($currentAvailableCredit, 2),
                        number_format($orderTotal, 2)
                    )
                );
            }

            $this->status = OrderStatus::CONFIRMED;
            $this->confirmed_at = now();
            $this->credit_reserved_at = now();
            $this->save();

            BuyerCreditUsageHistory::create([
                'team_id' => $buyer->team_id,
                'buyer_id' => $buyer->id,
                'transaction_type' => 'debit',
                'amount' => $orderTotal,
                'max_credit_limit_before' => 0,
                'max_credit_limit_after' => 0,
                'available_credit_before' => $currentAvailableCredit,
                'available_credit_after' => max(0.0, $currentAvailableCredit - $orderTotal),
                'credit_used_before' => $buyer->credit_exposure,
                'credit_used_after' => $buyer->credit_exposure + $orderTotal,
                'related_type' => self::class,
                'related_id' => $this->id,
                'description' => "Order {$this->order_number} confirmed",
                'created_by_id' => auth()->id(),
            ]);
        });
```

Also replace the pre-transaction check a few lines above:

```php
        $availableCredit = (float) $buyer->available_credit;
```

with:

```php
        $availableCredit = $buyer->derived_available_credit;
```

- [ ] **Step 4: Rewrite `restoreCredit()`**

Replace the whole `DB::transaction` block in `restoreCredit()` with:

```php
        \Illuminate\Support\Facades\DB::transaction(function () use ($orderTotal, $buyer): void {
            $availableBefore = $buyer->derived_available_credit;
            $usedBefore = $buyer->credit_exposure;

            // Releasing the full total removes this order from the exposure sum.
            // See the note above: this may be a re-entrant quiet write from
            // BuyerOrderObserver::updating().
            $this->credit_released = $this->total;
            $this->saveQuietly();

            if ($buyer->credit_status) {
                BuyerCreditUsageHistory::create([
                    'team_id' => $buyer->team_id,
                    'buyer_id' => $buyer->id,
                    'transaction_type' => 'credit',
                    'amount' => $orderTotal,
                    'max_credit_limit_before' => 0,
                    'max_credit_limit_after' => 0,
                    'available_credit_before' => $availableBefore,
                    'available_credit_after' => $availableBefore + $orderTotal,
                    'credit_used_before' => $usedBefore,
                    'credit_used_after' => max(0.0, $usedBefore - $orderTotal),
                    'related_type' => self::class,
                    'related_id' => $this->id,
                    'description' => "Order {$this->order_number} cancelled - credit restored",
                    'created_by_id' => auth()->id(),
                ]);
            }
        });
```

- [ ] **Step 5: Rewrite `reconcileReleasedCreditFor()`**

Replace the whole `DB::transaction` block with:

```php
        \Illuminate\Support\Facades\DB::transaction(function () use ($buyer, $delta, $target): void {
            $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            // Recompute against the locked row: a concurrent payment may have
            // already moved credit_released past our target.
            if (round((float) $locked->credit_released, 2) === round($target, 2)) {
                return;
            }

            $availableBefore = $buyer->derived_available_credit;
            $usedBefore = $buyer->credit_exposure;

            $this->credit_released = (string) $target;
            $this->saveQuietly();

            $transactionType = $delta > 0 ? 'credit' : 'debit';
            $description = $delta > 0
                ? "Order {$this->order_number} payment received - credit released"
                : "Order {$this->order_number} payment reversed - credit re-reserved";

            BuyerCreditUsageHistory::create([
                'team_id' => $buyer->team_id,
                'buyer_id' => $buyer->id,
                'transaction_type' => $transactionType,
                'amount' => abs($delta),
                'max_credit_limit_before' => 0,
                'max_credit_limit_after' => 0,
                'available_credit_before' => $availableBefore,
                'available_credit_after' => $availableBefore + $delta,
                'credit_used_before' => $usedBefore,
                'credit_used_after' => max(0.0, $usedBefore - $delta),
                'related_type' => self::class,
                'related_id' => $this->id,
                'description' => $description,
                'created_by_id' => auth()->id(),
            ]);
        });
```

- [ ] **Step 6: Rewrite `exceedsCreditLimit()`**

Replace the accessor body:

```php
    /**
     * Check if order total exceeds buyer's available credit.
     *
     * @return Attribute<bool, never>
     */
    protected function exceedsCreditLimit(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $buyer = $this->buyer;
                if ($buyer === null) {
                    return false;
                }

                $creditLimit = (float) $buyer->credit_limit;
                $orderTotal = (float) $this->total;

                return $creditLimit > 0 && $orderTotal > $buyer->derived_available_credit;
            },
        );
    }
```

- [ ] **Step 7: Point `CreditLimitWarningService` at the derived values**

In `app/Services/Erp/CreditLimitWarningService.php`, replace every
`(float) $buyer->credit_used` with `$buyer->credit_exposure` and every
`(float) $buyer->available_credit` with `$buyer->derived_available_credit`. The array
keys in the returned payloads (`credit_used`, `projected_credit_used`) keep their names
— they are a presentation contract consumed by Filament views.

Find them with: `grep -n "credit_used\|available_credit" app/Services/Erp/CreditLimitWarningService.php`

- [ ] **Step 8: Remove the remaining fake lock**

In `app/Models/BuyerCreditLimitRequest.php:186`, replace:

```php
            $this->lockForUpdate();
```

with:

```php
            $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
```

and use `$locked` for the status check that follows. Read the surrounding method before
editing — if the subsequent code re-reads `$this`, make it read `$locked` instead, or
the lock protects nothing again.

- [ ] **Step 9: Verify no fake locks survive**

Run: `grep -rn "^\s*\$[a-zA-Z]*->lockForUpdate();" app/`
Expected: no output. A bare `->lockForUpdate();` as a statement is always a no-op —
the method returns a builder and locks nothing.

- [ ] **Step 10: Verify the counters are no longer written**

Run: `grep -rn "credit_used = \|available_credit = " app/Models app/Services app/Actions`
Expected: no assignment to `$buyer->credit_used` or `$buyer->available_credit`. Array
keys named `credit_used_before` / `credit_used_after` inside
`BuyerCreditUsageHistory::create()` are the audit trail and correctly remain.

- [ ] **Step 11: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Credit/CreditCounterNotWrittenTest.php`
Expected: all five tests PASS.

- [ ] **Step 12: Run the full suite**

Run: `php vendor/bin/pest --parallel`
Expected: PASS. Failures here are most likely tests that seeded `credit_used` directly
to set up a scenario. Those must now seed a confirmed order instead — the seeded value
no longer means anything.

- [ ] **Step 13: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Models/BuyerOrder.php app/Models/BuyerCreditLimitRequest.php app/Services/Erp/CreditLimitWarningService.php tests/Feature/Erp/Credit/CreditCounterNotWrittenTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
php vendor/bin/pest --filter=arch
git add app/Models/BuyerOrder.php app/Models/BuyerCreditLimitRequest.php app/Services/Erp/CreditLimitWarningService.php tests/Feature/Erp/Credit/CreditCounterNotWrittenTest.php
git commit -m "fix: derive credit exposure at read time and remove the no-op lockForUpdate calls"
```

---

### Task 5: Point the admin surfaces at the derived value

**Files:**
- Modify: `app/Filament/Resources/BuyerCreditLimitOverviewResource.php:56` and `:121-130`
- Modify: `app/Filament/Resources/BuyerCreditLimitOverviewResource/Pages/ViewBuyerCreditLimit.php:72`
- Test: `tests/Feature/Filament/App/Resources/BuyerCreditLimitOverviewTest.php`

**Interfaces:**
- Consumes: `Company::withCreditExposure()`, `Company::$credit_exposure`
- Produces: no new PHP interface. The credit overview table's column keeps the visible
  label it has today; only its underlying attribute changes.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/App/Resources/BuyerCreditLimitOverviewTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Filament\Resources\BuyerCreditLimitOverviewResource\Pages\ListBuyerCreditLimits;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    actingAs($this->user);
    Filament::setTenant($this->team);

    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_status' => true,
        'credit_limit' => 100000,
        'credit_used' => 999999,
    ]);
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
});

it('shows derived exposure, not the stale stored counter', function (): void {
    BuyerOrder::factory()
        ->recycle($this->team)->recycle($this->currency)
        ->for($this->buyer, 'buyer')->for($this->request)
        ->create([
            'status' => OrderStatus::CONFIRMED,
            'total' => 7500,
            'credit_released' => 0,
            'credit_reserved_at' => now(),
        ]);

    livewire(ListBuyerCreditLimits::class)
        ->assertOk()
        ->assertTableColumnStateSet('credit_exposure', 7500.0, $this->buyer->fresh());
});

it('sorts by exposure without a SQL error', function (): void {
    livewire(ListBuyerCreditLimits::class)
        ->sortTable('credit_exposure')
        ->assertOk();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Filament/App/Resources/BuyerCreditLimitOverviewTest.php`
Expected: FAIL — the table has no `credit_exposure` column.

- [ ] **Step 3: Apply the scope in the resource query**

In `app/Filament/Resources/BuyerCreditLimitOverviewResource.php`, replace
`getEloquentQuery()`:

```php
    /**
     * @return Builder<Company>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('team_id', $team?->getKey())
            ->where('is_buyer', true)
            ->withCreditExposure()
            ->with(['creditLimitRequests', 'creditUsageHistory']);
    }
```

- [ ] **Step 4: Repoint the table column**

At `app/Filament/Resources/BuyerCreditLimitOverviewResource.php:56`, change
`TextColumn::make('credit_used')` to `TextColumn::make('credit_exposure')` and keep
whatever `->label()`, `->money()` and `->sortable()` calls it already carries. If it has
no explicit `->label()`, add `->label('Credit Used')` so the visible heading does not
change for users.

- [ ] **Step 5: Repoint the detail page entry**

At `app/Filament/Resources/BuyerCreditLimitOverviewResource/Pages/ViewBuyerCreditLimit.php:72`,
change `TextEntry::make('credit_used')` to `TextEntry::make('credit_exposure')`, again
preserving the existing label or adding `->label('Credit Used')`.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Filament/App/Resources/BuyerCreditLimitOverviewTest.php`
Expected: both tests PASS.

- [ ] **Step 7: Check no admin surface still reads the stored columns**

Run: `grep -rn "'credit_used'\|'available_credit'" app/Filament`
Expected: no hits. Each remaining hit is a surface still showing a value nothing
maintains — repoint it the same way.

- [ ] **Step 8: Run the search smoke test and the full suite**

```bash
php vendor/bin/pest tests/Feature/Filament/SearchableColumnsSmokeTest.php
php vendor/bin/pest --parallel
```
Expected: PASS. The smoke test exists precisely to catch a column made `searchable()`
that is not a real DB column — `credit_exposure` is a select alias, which searches fine
but must not be passed to `->searchable()` without a custom query.

- [ ] **Step 9: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Filament/Resources/BuyerCreditLimitOverviewResource.php app/Filament/Resources/BuyerCreditLimitOverviewResource/Pages/ViewBuyerCreditLimit.php tests/Feature/Filament/App/Resources/BuyerCreditLimitOverviewTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Filament tests/Feature/Filament/App/Resources/BuyerCreditLimitOverviewTest.php
git commit -m "fix: show derived credit exposure in the buyer credit overview"
```

---

## Follow-up, deliberately not in this plan

Dropping `companies.credit_used` and `companies.available_credit` is a **contract**
migration and must not ship in the same deploy as the code that stops writing them. The
sequence is:

1. Ship Tasks 1–5. Both columns still exist; nothing writes them.
2. Schedule `erp:reconcile-credit-exposure` daily. It will show growing drift — that is
   expected and is the proof that the columns are now dead, not that anything is wrong.
3. After a full billing cycle with no surface found reading them, drop both columns in
   a separate migration and retire or repurpose the reconciliation command.

Record steps 2–3 as an OpenSpec change so they do not get lost.

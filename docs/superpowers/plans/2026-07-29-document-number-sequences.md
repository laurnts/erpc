# Document Number Sequences Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace 13 copies of an unsafe read-max-then-increment document numbering routine with one locked counter row, fixing a lexical-sort bug that hard-stops document creation at 10 000 documents per team per year.

**Architecture:** A `document_number_sequences` table holds one counter row per
(team, document key, period). A single `DocumentNumberAllocator` service takes the next
value under `SELECT ... FOR UPDATE` inside a transaction. Formatting stays at each call
site, because the four number formats genuinely differ (dashed, roman-month, slashed) —
only the unsafe part is centralised. This is §20.4 of
`/Users/laurnts/Sites/pos/ARCHITECTURE.md` reduced to what erpc needs: the counter row,
without the single-consumer queue that exists there only to keep a storefront checkout
path free.

**Tech Stack:** Laravel 12, PostgreSQL 15+, Pest 4.

## Global Constraints

- All PHP files declare `declare(strict_types=1);`
- All classes `final` by default; services `final readonly`
- Comparisons use `===` / `!==` exclusively
- Tooling runs through the Docker wrapper: `php vendor/bin/<tool>`
- Before finalizing any change: `php vendor/bin/rector process <changed files>` then `php vendor/bin/pint --dirty`
- Existing number **formats must not change** — historical documents and their PDFs
  are already issued. Only the allocation mechanism changes.

## Decision required before Task 3

Today's read-max approach **refills gaps**: if document 0007 is never persisted (a
failed save, a discarded draft), the next create takes 0007 again. A counter row does
not refill gaps — numbers become strictly monotonic per period, and a rolled-back
create burns its number.

For quotes, orders, payments, shipments and P&L this is harmless and is the better
behaviour (a reused number is worse than a missing one). For **buyer invoices** it
depends on the jurisdiction's numbering rules. Confirm with the business before Task 3
whether invoice numbers must be gapless. If they must, invoice numbers need assignment
at *issue* rather than at *create*, which is a separate change — record it as an
OpenSpec follow-up and keep `BuyerInvoice` on the old path until then, rather than
guessing.

## Inventory — the 13 call sites

| # | Site | Column | Format | Family |
|---|---|---|---|---|
| 1 | `app/Models/BuyerQuote.php:720` | `quote_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 2 | `app/Models/BuyerOrder.php:663` | `order_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 3 | `app/Models/BuyerInvoice.php:523` | `invoice_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 4 | `app/Models/BuyerPayment.php:190` | `payment_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 5 | `app/Observers/RequestObserver.php:49` | `request_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 6 | `app/Observers/ProjectObserver.php` | `project_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 7 | `app/Observers/ShipmentObserver.php` | `shipment_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 8 | `app/Observers/SupplierInvoiceObserver.php` | `reference_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 9 | `app/Observers/SupplierPaymentObserver.php` | `payment_number` | `{PREFIX}-{YYYY}-{0000}` | A |
| 10 | `app/Observers/SupplierOrderObserver.php:60` | `po_number` | `{PREFIX}-{YYYY}-{0000}[-A]` | special |
| 11 | `app/Models/QuotationEvaluation.php:134` | `qe_number` | `{000}-DS/QE/{ROMAN}/{YYYY}` | B |
| 12 | `app/Models/ProfitAndLoss.php:143` | `pnl_number` | `{0000}/EL-PNL/{ROMAN}/{YYYY}` | B |
| 13 | `app/Models/AcceptanceReport.php:131` | `report_number` | `AR-{YYYY}-{0000}` | C |

Family A and C share the lexical-sort bug (`orderByDesc('<x>_number')` puts `'9999'`
above `'10000'`). Family B has a different bug: `orderByDesc('id')` reads the
last-*inserted* row rather than the highest sequence, and omits `withTrashed()` —
though neither `QuotationEvaluation` nor `ProfitAndLoss` uses soft deletes, so only the
first half bites. Site 13 is already correct but loads every number for the team/year
into PHP to compute a max.

---

### Task 1: The sequence table and allocator

**Files:**
- Create: `database/migrations/2026_07_29_110000_create_document_number_sequences_table.php`
- Create: `app/Services/Erp/Numbering/DocumentNumberAllocator.php`
- Test: `tests/Unit/Erp/Numbering/DocumentNumberAllocatorTest.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  ```php
  final readonly class DocumentNumberAllocator
  {
      public function next(int $teamId, string $key, string $period): int;
      public function peek(int $teamId, string $key, string $period): int;
      public function seed(int $teamId, string $key, string $period, int $nextValue): void;
  }
  ```
  `next()` returns the allocated sequence integer and advances the counter.
  `peek()` returns what `next()` would return, without advancing (tests and backfill
  verification only). `seed()` sets the counter, used by the Task 2 backfill.
  `$key` values are the document keys listed in Task 3–6, e.g. `'buyer_quote'`.
  `$period` is the calendar year as a string, e.g. `'2026'`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Erp/Numbering/DocumentNumberAllocatorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use App\Services\Erp\Numbering\DocumentNumberAllocator;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->allocator = app(DocumentNumberAllocator::class);
});

it('starts a fresh sequence at 1', function (): void {
    expect($this->allocator->next($this->team->getKey(), 'buyer_quote', '2026'))->toBe(1);
});

it('advances monotonically', function (): void {
    $teamId = $this->team->getKey();

    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(1);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(2);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(3);
});

it('keeps sequences independent per key', function (): void {
    $teamId = $this->team->getKey();

    $this->allocator->next($teamId, 'buyer_quote', '2026');
    $this->allocator->next($teamId, 'buyer_quote', '2026');

    expect($this->allocator->next($teamId, 'buyer_order', '2026'))->toBe(1);
});

it('keeps sequences independent per period', function (): void {
    $teamId = $this->team->getKey();

    $this->allocator->next($teamId, 'buyer_quote', '2026');

    expect($this->allocator->next($teamId, 'buyer_quote', '2027'))->toBe(1);
});

it('keeps sequences independent per team', function (): void {
    $other = Team::factory()->create();

    $this->allocator->next($this->team->getKey(), 'buyer_quote', '2026');

    expect($this->allocator->next($other->getKey(), 'buyer_quote', '2026'))->toBe(1);
});

it('crosses 9999 without regressing', function (): void {
    $teamId = $this->team->getKey();

    $this->allocator->seed($teamId, 'buyer_quote', '2026', 9999);

    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(9999);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(10000);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(10001);
});

it('peeks without advancing', function (): void {
    $teamId = $this->team->getKey();

    expect($this->allocator->peek($teamId, 'buyer_quote', '2026'))->toBe(1);
    expect($this->allocator->peek($teamId, 'buyer_quote', '2026'))->toBe(1);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(1);
});

it('seeds an existing sequence to a new value', function (): void {
    $teamId = $this->team->getKey();

    $this->allocator->next($teamId, 'buyer_quote', '2026');
    $this->allocator->seed($teamId, 'buyer_quote', '2026', 500);

    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(500);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Unit/Erp/Numbering/DocumentNumberAllocatorTest.php`
Expected: FAIL — `Target class [App\Services\Erp\Numbering\DocumentNumberAllocator] does not exist.`

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_29_110000_create_document_number_sequences_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One counter row per (team, document key, period). Allocation takes the row
 * under SELECT ... FOR UPDATE, so concurrent creates serialise on the counter
 * instead of racing a read-max query.
 *
 * @see /Users/laurnts/Sites/pos/ARCHITECTURE.md §20.4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('period');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['team_id', 'key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_sequences');
    }
};
```

- [ ] **Step 4: Write the contention exception**

The allocator in Step 5 references this class, so it must exist first.

Create `app/Services/Erp/Numbering/SequenceContendedException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Erp\Numbering;

use RuntimeException;

/**
 * Thrown when two allocations create the same counter row simultaneously. The
 * caller retries; the second attempt finds the row and takes the lock path.
 */
final class SequenceContendedException extends RuntimeException {}
```

- [ ] **Step 5: Write the allocator**

Create `app/Services/Erp/Numbering/DocumentNumberAllocator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Erp\Numbering;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Allocates document sequence numbers from a locked counter row.
 *
 * Formatting stays at the call site: the four document-number formats in this
 * system differ structurally (dashed, roman-month, slashed, suffixed), and a
 * generic formatter would be harder to read than the sprintf() it replaced.
 * Only the part that was unsafe — deciding which integer comes next — lives here.
 *
 * Numbers are strictly monotonic per (team, key, period). A rolled-back create
 * burns its number; gaps are not refilled. That is deliberate: reusing a number
 * that briefly existed is worse than skipping one.
 */
final readonly class DocumentNumberAllocator
{
    private const string TABLE = 'document_number_sequences';

    /**
     * Take the next sequence value and advance the counter.
     */
    public function next(int $teamId, string $key, string $period): int
    {
        return DB::transaction(function () use ($teamId, $key, $period): int {
            $row = DB::table(self::TABLE)
                ->where('team_id', $teamId)
                ->where('key', $key)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $this->insertSequence($teamId, $key, $period, 2);

                return 1;
            }

            $allocated = (int) $row->next_value;

            DB::table(self::TABLE)
                ->where('id', $row->id)
                ->update([
                    'next_value' => $allocated + 1,
                    'updated_at' => now(),
                ]);

            return $allocated;
        });
    }

    /**
     * What next() would return, without advancing. Diagnostics and backfill
     * verification only — never use this to assign a number.
     */
    public function peek(int $teamId, string $key, string $period): int
    {
        $value = DB::table(self::TABLE)
            ->where('team_id', $teamId)
            ->where('key', $key)
            ->where('period', $period)
            ->value('next_value');

        return $value === null ? 1 : (int) $value;
    }

    /**
     * Set the counter so the next allocation returns $nextValue.
     */
    public function seed(int $teamId, string $key, string $period, int $nextValue): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['team_id' => $teamId, 'key' => $key, 'period' => $period],
            ['next_value' => $nextValue, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * Insert a fresh counter row. Two concurrent first-allocations both find no
     * row; the unique index rejects the loser, which then retries through next()
     * and takes the lock path.
     */
    private function insertSequence(int $teamId, string $key, string $period, int $nextValue): void
    {
        try {
            DB::table(self::TABLE)->insert([
                'team_id' => $teamId,
                'key' => $key,
                'period' => $period,
                'next_value' => $nextValue,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'document_number_sequences_team_id_key_period_unique')) {
                throw $e;
            }

            throw new SequenceContendedException(
                sprintf('Sequence %s/%s/%d was created concurrently.', $key, $period, $teamId),
                previous: $e,
            );
        }
    }
}
```

- [ ] **Step 6: Make the first-allocation race self-healing**

Modify `app/Services/Erp/Numbering/DocumentNumberAllocator.php` — replace the body of
`next()` with a retrying wrapper and rename the existing body to `attempt()`:

```php
    /**
     * Take the next sequence value and advance the counter.
     */
    public function next(int $teamId, string $key, string $period): int
    {
        try {
            return $this->attempt($teamId, $key, $period);
        } catch (SequenceContendedException) {
            return $this->attempt($teamId, $key, $period);
        }
    }

    private function attempt(int $teamId, string $key, string $period): int
    {
        return DB::transaction(function () use ($teamId, $key, $period): int {
            $row = DB::table(self::TABLE)
                ->where('team_id', $teamId)
                ->where('key', $key)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $this->insertSequence($teamId, $key, $period, 2);

                return 1;
            }

            $allocated = (int) $row->next_value;

            DB::table(self::TABLE)
                ->where('id', $row->id)
                ->update([
                    'next_value' => $allocated + 1,
                    'updated_at' => now(),
                ]);

            return $allocated;
        });
    }
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Unit/Erp/Numbering/DocumentNumberAllocatorTest.php`
Expected: all 8 tests PASS.

- [ ] **Step 8: Lint and analyse**

```bash
php vendor/bin/rector process app/Services/Erp/Numbering tests/Unit/Erp/Numbering database/migrations/2026_07_29_110000_create_document_number_sequences_table.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
```
Expected: no diffs on re-run, PHPStan PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/Erp/Numbering tests/Unit/Erp/Numbering database/migrations/2026_07_29_110000_create_document_number_sequences_table.php
git commit -m "feat: add DocumentNumberAllocator backed by a locked counter row"
```

---

### Task 2: Backfill sequences from existing documents

Counters must start above every number already issued, or the first allocation after
deploy collides with history and the unique index rejects every create.

**Files:**
- Create: `app/Console/Commands/BackfillDocumentNumberSequences.php`
- Test: `tests/Feature/Erp/Numbering/BackfillDocumentNumberSequencesTest.php`

**Interfaces:**
- Consumes: `DocumentNumberAllocator::seed()`, `DocumentNumberAllocator::peek()`
- Produces: artisan command `erp:backfill-document-sequences` (accepts `--dry-run`).
  Idempotent: running it twice is a no-op. Never lowers a counter.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Numbering/BackfillDocumentNumberSequencesTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Services\Erp\Numbering\DocumentNumberAllocator;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->allocator = app(DocumentNumberAllocator::class);
});

it('seeds the counter above the highest existing number', function (): void {
    foreach (['BQ-2026-0007', 'BQ-2026-0042', 'BQ-2026-0009'] as $number) {
        BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->currency)
            ->for($this->request)
            ->create(['quote_number' => $number]);
    }

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(43);
});

it('is not fooled by lexical ordering past 9999', function (): void {
    foreach (['BQ-2026-9999', 'BQ-2026-10000'] as $number) {
        BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->currency)
            ->for($this->request)
            ->create(['quote_number' => $number]);
    }

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(10001);
});

it('counts soft-deleted documents so their numbers are never reissued', function (): void {
    $quote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0099']);
    $quote->delete();

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(100);
});

it('is idempotent and never lowers a counter', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0005']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();
    $this->allocator->next($this->team->getKey(), 'buyer_quote', '2026');
    $this->allocator->next($this->team->getKey(), 'buyer_quote', '2026');
    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(8);
});

it('changes nothing on a dry run', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-2026-0005']);

    $this->artisan('erp:backfill-document-sequences', ['--dry-run' => true])->assertSuccessful();

    expect($this->allocator->peek($this->team->getKey(), 'buyer_quote', '2026'))->toBe(1);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/BackfillDocumentNumberSequencesTest.php`
Expected: FAIL — `The command "erp:backfill-document-sequences" does not exist.`

- [ ] **Step 3: Write the command**

Create `app/Console/Commands/BackfillDocumentNumberSequences.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Erp\Numbering\DocumentNumberAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds document_number_sequences from numbers already issued, so the first
 * allocation after cutover cannot collide with history.
 *
 * The sequence integer is extracted in SQL rather than PHP so this stays a
 * single aggregate query per document type regardless of row count. Each entry
 * pairs a table with a PostgreSQL regex whose first capture group is the
 * sequence and whose second is the period (calendar year).
 */
final class BackfillDocumentNumberSequences extends Command
{
    protected $signature = 'erp:backfill-document-sequences {--dry-run : Report what would change without writing}';

    protected $description = 'Seed document number sequence counters from existing documents';

    /**
     * key => [table, column, regex]. The regex must capture the sequence integer
     * as group 1 and the four-digit period as group 2.
     */
    private const array SOURCES = [
        'request' => ['requests', 'request_number', '^.+-(\d{4})-(\d+)$'],
        'project' => ['projects', 'project_number', '^.+-(\d{4})-(\d+)$'],
        'buyer_quote' => ['buyer_quotes', 'quote_number', '^.+-(\d{4})-(\d+)$'],
        'buyer_order' => ['buyer_orders', 'order_number', '^.+-(\d{4})-(\d+)$'],
        'buyer_invoice' => ['buyer_invoices', 'invoice_number', '^.+-(\d{4})-(\d+)$'],
        'buyer_payment' => ['buyer_payments', 'payment_number', '^.+-(\d{4})-(\d+)$'],
        'supplier_order' => ['supplier_orders', 'po_number', '^.+-(\d{4})-(\d+)(?:-[A-Z])?$'],
        'supplier_invoice' => ['supplier_invoices', 'reference_number', '^.+-(\d{4})-(\d+)$'],
        'supplier_payment' => ['supplier_payments', 'payment_number', '^.+-(\d{4})-(\d+)$'],
        'shipment' => ['shipments', 'shipment_number', '^.+-(\d{4})-(\d+)$'],
        'acceptance_report' => ['acceptance_reports', 'report_number', '^AR-(\d{4})-(\d+)$'],
        'quotation_evaluation' => ['quotation_evaluations', 'qe_number', '^(\d+)-DS/QE/[IVX]+/(\d{4})$'],
        'profit_and_loss' => ['profit_and_losses', 'pnl_number', '^(\d+)/EL-PNL/[IVX]+/(\d{4})$'],
    ];

    /**
     * Keys whose regex captures the sequence first and the period second, i.e.
     * the reverse of the dashed formats.
     */
    private const array SEQUENCE_FIRST = ['quotation_evaluation', 'profit_and_loss'];

    public function handle(DocumentNumberAllocator $allocator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (self::SOURCES as $key => [$table, $column, $regex]) {
            $sequenceFirst = in_array($key, self::SEQUENCE_FIRST, true);
            $periodGroup = $sequenceFirst ? 2 : 1;
            $sequenceGroup = $sequenceFirst ? 1 : 2;

            $rows = DB::table($table)
                ->selectRaw(
                    'team_id, '
                    ."(regexp_match({$column}, ?))[{$periodGroup}] AS period, "
                    ."MAX(((regexp_match({$column}, ?))[{$sequenceGroup}])::bigint) AS max_sequence",
                    [$regex, $regex],
                )
                ->whereRaw("{$column} ~ ?", [$regex])
                ->groupByRaw("team_id, (regexp_match({$column}, ?))[{$periodGroup}]", [$regex])
                ->get();

            foreach ($rows as $row) {
                $teamId = (int) $row->team_id;
                $period = (string) $row->period;
                $target = ((int) $row->max_sequence) + 1;
                $current = $allocator->peek($teamId, $key, $period);

                if ($current >= $target) {
                    continue;
                }

                $this->line(sprintf(
                    '%s team=%d period=%s: %d -> %d%s',
                    $key, $teamId, $period, $current, $target, $dryRun ? ' (dry run)' : '',
                ));

                if (! $dryRun) {
                    $allocator->seed($teamId, $key, $period, $target);
                }
            }
        }

        $this->info($dryRun ? 'Dry run complete — nothing written.' : 'Sequence backfill complete.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/BackfillDocumentNumberSequencesTest.php`
Expected: all 5 tests PASS.

If the `regexp_match` calls fail with `function regexp_match(...) does not exist`, the
database is not PostgreSQL — check `.env.testing` sets `DB_CONNECTION=pgsql`. This
command is PostgreSQL-only by design; the project constrains itself to PostgreSQL.

- [ ] **Step 5: Dry-run against a real database copy**

```bash
php artisan erp:backfill-document-sequences --dry-run
```
Expected: one line per (key, team, period) present in the data, each showing `1 -> N`.
Sanity-check two of them by hand against `SELECT MAX(...)` before proceeding. A key
that prints nothing means either no documents exist for it or the regex does not match
the real format — verify which.

- [ ] **Step 6: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Console/Commands/BackfillDocumentNumberSequences.php tests/Feature/Erp/Numbering
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Console/Commands/BackfillDocumentNumberSequences.php tests/Feature/Erp/Numbering
git commit -m "feat: add erp:backfill-document-sequences to seed counters from issued numbers"
```

---

### Task 3: Move the four model statics onto the allocator

**Files:**
- Modify: `app/Models/BuyerQuote.php:720-745`
- Modify: `app/Models/BuyerOrder.php:663-690`
- Modify: `app/Models/BuyerInvoice.php:523-547`
- Modify: `app/Models/BuyerPayment.php:190-215`
- Test: `tests/Feature/Erp/Numbering/ModelDocumentNumberTest.php`

**Interfaces:**
- Consumes: `DocumentNumberAllocator::next(int $teamId, string $key, string $period): int`
- Produces: `generateNextNumber(int $teamId): string` keeps its existing signature and
  output format on all four models, so `BuyerQuoteObserver`, `BuyerOrderObserver`,
  `BuyerInvoiceObserver`, `BuyerPaymentObserver` and `BuyerQuote::duplicate()` need no
  change. Sequence keys: `buyer_quote`, `buyer_order`, `buyer_invoice`, `buyer_payment`.

> **Gate:** do not start this task until the gapless-invoice question in *Decision
> required before Task 3* has an answer. If invoices must be gapless, drop
> `BuyerInvoice` from this task and leave it on the old path.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Numbering/ModelDocumentNumberTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

it('keeps the existing quote number format', function (): void {
    $number = BuyerQuote::generateNextNumber($this->team->getKey());

    expect($number)->toMatch('/^BQ-\d{4}-\d{4}$/');
});

it('does not reissue a number after the 9999 boundary', function (): void {
    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create(['quote_number' => 'BQ-'.date('Y').'-9999']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    expect(BuyerQuote::generateNextNumber($this->team->getKey()))
        ->toBe('BQ-'.date('Y').'-10000');
});

it('never issues the same number twice across many allocations', function (): void {
    $teamId = $this->team->getKey();

    $numbers = collect(range(1, 50))
        ->map(fn (): string => BuyerQuote::generateNextNumber($teamId))
        ->all();

    expect($numbers)->toHaveCount(50)
        ->and(array_unique($numbers))->toHaveCount(50);
});
```

The third test is the one that fails hardest on today's code: `generateNextNumber()`
reads the database and nothing is persisted between calls, so it returns the *same*
number 50 times.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/ModelDocumentNumberTest.php`
Expected: FAIL on "does not reissue a number after the 9999 boundary" (returns
`BQ-YYYY-10000`? no — returns `BQ-YYYY-0001`, because `'BQ-2026-9999'` is the lexical
max and the regex captures `9999`… verify the actual failure message) and FAIL on
"never issues the same number twice" with 1 unique value instead of 50.

- [ ] **Step 3: Rewrite `BuyerQuote::generateNextNumber()`**

In `app/Models/BuyerQuote.php`, replace the whole `generateNextNumber` method body:

```php
    /**
     * Generate the next quote number for the given team.
     *
     * Allocation is a counter row (DocumentNumberAllocator), not a read-max over
     * existing rows: two concurrent creates cannot receive the same number, and
     * the sequence does not regress when it crosses 9999 (a string sort put
     * '9999' above '10000').
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new \App\Data\TeamErpSettings;
        $prefix = $settings->buyer_quote_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)->next($teamId, 'buyer_quote', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
```

Add to the imports at the top of the file:

```php
use App\Services\Erp\Numbering\DocumentNumberAllocator;
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/ModelDocumentNumberTest.php`
Expected: all three tests PASS.

- [ ] **Step 5: Apply the identical change to `BuyerOrder`**

In `app/Models/BuyerOrder.php`, replace the whole `generateNextNumber` method body:

```php
    /**
     * Generate the next order number for the given team.
     *
     * @see BuyerQuote::generateNextNumber() for why this is a counter row.
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->buyer_order_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)->next($teamId, 'buyer_order', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
```

Add `use App\Services\Erp\Numbering\DocumentNumberAllocator;` to the imports.

- [ ] **Step 6: Apply the identical change to `BuyerInvoice`**

In `app/Models/BuyerInvoice.php`, replace the whole `generateNextNumber` method body:

```php
    /**
     * Generate the next invoice number for the given team.
     *
     * @see BuyerQuote::generateNextNumber() for why this is a counter row.
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->buyer_invoice_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)->next($teamId, 'buyer_invoice', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
```

Add `use App\Services\Erp\Numbering\DocumentNumberAllocator;` to the imports.

- [ ] **Step 7: Apply the identical change to `BuyerPayment`**

In `app/Models/BuyerPayment.php`, replace the whole `generateNextNumber` method body:

```php
    /**
     * Generate the next payment number for the given team.
     *
     * @see BuyerQuote::generateNextNumber() for why this is a counter row.
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->buyer_payment_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)->next($teamId, 'buyer_payment', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
```

Add `use App\Services\Erp\Numbering\DocumentNumberAllocator;` to the imports.

- [ ] **Step 8: Run the four existing number tests**

```bash
php vendor/bin/pest tests/Feature/Erp/BuyerQuoteTest.php tests/Feature/Erp/BuyerOrderTest.php tests/Feature/Erp/BuyerInvoiceTest.php tests/Feature/Erp/BuyerPaymentTest.php
```
Expected: PASS. `BuyerQuoteTest.php:690`, `BuyerOrderTest.php:584`,
`BuyerInvoiceTest.php:479` and `BuyerPaymentTest.php:295` all call
`generateNextNumber()` directly and assert on format — they should still pass. If one
asserts a *specific* sequence value like `-0001` after creating a document, it was
relying on gap-refilling; update the assertion to the monotonic value and note why in
the test.

- [ ] **Step 9: Run the full suite**

Run: `php vendor/bin/pest --parallel`
Expected: PASS.

- [ ] **Step 10: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Models/BuyerQuote.php app/Models/BuyerOrder.php app/Models/BuyerInvoice.php app/Models/BuyerPayment.php tests/Feature/Erp/Numbering/ModelDocumentNumberTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Models/BuyerQuote.php app/Models/BuyerOrder.php app/Models/BuyerInvoice.php app/Models/BuyerPayment.php tests/Feature/Erp/Numbering/ModelDocumentNumberTest.php
git commit -m "fix: allocate buyer document numbers from a counter row instead of read-max"
```

---

### Task 4: Move the five straightforward observers onto the allocator

**Files:**
- Modify: `app/Observers/RequestObserver.php:49-75`
- Modify: `app/Observers/ProjectObserver.php` (`generateProjectNumber`)
- Modify: `app/Observers/ShipmentObserver.php` (`generateShipmentNumber`)
- Modify: `app/Observers/SupplierInvoiceObserver.php` (`generateReferenceNumber`)
- Modify: `app/Observers/SupplierPaymentObserver.php` (`generatePaymentNumber`)
- Test: `tests/Feature/Erp/Numbering/ObserverDocumentNumberTest.php`

**Interfaces:**
- Consumes: `DocumentNumberAllocator::next(int $teamId, string $key, string $period): int`
- Produces: no signature changes. Sequence keys: `request`, `project`, `shipment`,
  `supplier_invoice`, `supplier_payment`.

`SupplierOrderObserver` is deliberately excluded — see Task 5.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Numbering/ObserverDocumentNumberTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
});

it('assigns a distinct request number to every created request', function (): void {
    $numbers = collect(range(1, 25))
        ->map(fn (): string => (string) Request::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->create()
            ->request_number)
        ->all();

    expect(array_unique($numbers))->toHaveCount(25);
});

it('does not reuse a soft-deleted request number', function (): void {
    $first = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
    $firstNumber = (string) $first->request_number;
    $first->delete();

    $second = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

    expect((string) $second->request_number)->not->toBe($firstNumber);
});

it('continues past the 9999 boundary', function (): void {
    Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create(['request_number' => 'REQ-'.date('Y').'-9999']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    $next = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

    expect((string) $next->request_number)->toBe('REQ-'.date('Y').'-10000');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/ObserverDocumentNumberTest.php`
Expected: the 9999-boundary test FAILS (returns `REQ-YYYY-0001`). The first two may
already pass, because each create *is* persisted between calls — keep them as
regression cover.

- [ ] **Step 3: Rewrite `RequestObserver::generateRequestNumber()`**

In `app/Observers/RequestObserver.php`, replace the whole method:

```php
    /**
     * Generate a unique request number (REQ-YYYY-NNNN format).
     *
     * @see \App\Services\Erp\Numbering\DocumentNumberAllocator for why this is a
     *      counter row rather than a read-max over existing numbers.
     */
    private function generateRequestNumber(Request $request): string
    {
        $team = $request->team ?? ($request->team_id !== null ? Team::find($request->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->request_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)
            ->next((int) $request->team_id, 'request', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
```

Add `use App\Services\Erp\Numbering\DocumentNumberAllocator;` to the imports.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/ObserverDocumentNumberTest.php`
Expected: all three tests PASS.

- [ ] **Step 5: Apply the same shape to the remaining four observers**

For each of `ProjectObserver::generateProjectNumber`,
`ShipmentObserver::generateShipmentNumber`,
`SupplierInvoiceObserver::generateReferenceNumber` and
`SupplierPaymentObserver::generatePaymentNumber`: keep the method's existing signature
and its existing `$prefix`/`$year`/`sprintf` lines, delete the query-and-regex block,
and replace the `$nextNumber` computation with:

```php
        $sequence = app(DocumentNumberAllocator::class)
            ->next((int) $model->team_id, '<key>', $year);
```

using `$model` = the observer's own parameter variable and `<key>` = `project`,
`shipment`, `supplier_invoice`, `supplier_payment` respectively. Add
`use App\Services\Erp\Numbering\DocumentNumberAllocator;` to each file's imports.

- [ ] **Step 6: Verify no read-max survives in the five files**

Run: `grep -n "orderByDesc" app/Observers/RequestObserver.php app/Observers/ProjectObserver.php app/Observers/ShipmentObserver.php app/Observers/SupplierInvoiceObserver.php app/Observers/SupplierPaymentObserver.php`
Expected: no output.

- [ ] **Step 7: Run the full suite**

Run: `php vendor/bin/pest --parallel`
Expected: PASS.

- [ ] **Step 8: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Observers/RequestObserver.php app/Observers/ProjectObserver.php app/Observers/ShipmentObserver.php app/Observers/SupplierInvoiceObserver.php app/Observers/SupplierPaymentObserver.php tests/Feature/Erp/Numbering/ObserverDocumentNumberTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Observers tests/Feature/Erp/Numbering/ObserverDocumentNumberTest.php
git commit -m "fix: allocate request, project, shipment and supplier document numbers from the counter row"
```

---

### Task 5: Supplier PO base numbers

`SupplierOrderObserver::generatePoNumber()` is the one site with real logic: several
supplier orders on the same request share a base number and get an `-A`/`-B` suffix.
Only the **base number** path allocates from the sequence; the suffix path must keep
reading the request's existing orders.

Its existing max-finding loop is already correct (it iterates and takes a true max), so
this task fixes the race and the O(n) load, not a wrong-number bug.

**Files:**
- Modify: `app/Observers/SupplierOrderObserver.php:60-114`
- Test: `tests/Feature/Erp/Numbering/SupplierOrderNumberTest.php`

**Interfaces:**
- Consumes: `DocumentNumberAllocator::next(int $teamId, string $key, string $period): int`
- Produces: no signature change. Sequence key: `supplier_order`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Numbering/SupplierOrderNumberTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
});

function makeRequestFor(Team $team, Company $buyer): Request
{
    return Request::factory()->recycle($team)->recycle($buyer)->create();
}

it('gives the second order on the same request a suffixed base number', function (): void {
    $request = makeRequestFor($this->team, $this->buyer);

    $first = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($request)->create();
    $second = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($request)->create();

    $base = (string) $first->po_number;

    expect((string) $second->po_number)->toBe($base.'-A');
});

it('gives a different request a new base number', function (): void {
    $requestA = makeRequestFor($this->team, $this->buyer);
    $requestB = makeRequestFor($this->team, $this->buyer);

    $a = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($requestA)->create();
    $b = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($requestB)->create();

    expect((string) $b->po_number)->not->toBe((string) $a->po_number);
});

it('continues base numbers past the 9999 boundary', function (): void {
    $seedRequest = makeRequestFor($this->team, $this->buyer);
    SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for($seedRequest)
        ->create(['po_number' => 'PO-'.date('Y').'-9999']);

    $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

    $next = SupplierOrder::factory()
        ->recycle($this->team)->recycle($this->currency)->recycle($this->supplier)
        ->for(makeRequestFor($this->team, $this->buyer))
        ->create();

    expect((string) $next->po_number)->toBe('PO-'.date('Y').'-10000');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/SupplierOrderNumberTest.php`
Expected: the 9999-boundary test FAILS. The first two should pass — they cover the
suffix behaviour this task must not break.

- [ ] **Step 3: Rewrite the base-number path only**

In `app/Observers/SupplierOrderObserver.php`, replace everything from the
`// Get the highest sequence number for this team and year (for new base number)`
comment to the end of the method with:

```php
        // New base number for this request. Only this path consumes the sequence:
        // the suffix path above reuses an already-allocated base.
        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)
            ->next((int) $supplierOrder->team_id, 'supplier_order', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
```

Leave the whole `$existingOrdersForRequest` block above it untouched. Add
`use App\Services\Erp\Numbering\DocumentNumberAllocator;` to the imports.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/SupplierOrderNumberTest.php`
Expected: all three tests PASS.

- [ ] **Step 5: Run the full suite, lint, analyse, commit**

```bash
php vendor/bin/pest --parallel
php vendor/bin/rector process app/Observers/SupplierOrderObserver.php tests/Feature/Erp/Numbering/SupplierOrderNumberTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
git add app/Observers/SupplierOrderObserver.php tests/Feature/Erp/Numbering/SupplierOrderNumberTest.php
git commit -m "fix: allocate supplier PO base numbers from the counter row, keeping split-PO suffixes"
```

---

### Task 6: QE, P&L and acceptance report numbers

These three keep their distinctive formats. `QuotationEvaluation` and `ProfitAndLoss`
additionally fix a second bug: `orderByDesc('id')` reads the last-*inserted* row of the
year rather than the highest sequence, so an out-of-order insert silently restarts the
count.

**Files:**
- Modify: `app/Models/QuotationEvaluation.php:134-155`
- Modify: `app/Models/ProfitAndLoss.php:143-165`
- Modify: `app/Models/AcceptanceReport.php:131-150`
- Test: `tests/Feature/Erp/Numbering/SpecialFormatNumberTest.php`

**Interfaces:**
- Consumes: `DocumentNumberAllocator::next(int $teamId, string $key, string $period): int`
- Produces: `generateQeNumber(int $teamId): string`,
  `generatePnlNumber(int $teamId): string`,
  `generateReportNumber(int $teamId): string` — all unchanged signatures and formats.
  Sequence keys: `quotation_evaluation`, `profit_and_loss`, `acceptance_report`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Numbering/SpecialFormatNumberTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\AcceptanceReport;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Team;
use App\Support\RomanNumerals;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
});

it('keeps the QE number format', function (): void {
    $number = QuotationEvaluation::generateQeNumber($this->team->getKey());

    expect($number)->toBe(sprintf(
        '%03d-DS/QE/%s/%d', 1, RomanNumerals::month(now()->month), now()->year,
    ));
});

it('keeps the PNL number format', function (): void {
    $number = ProfitAndLoss::generatePnlNumber($this->team->getKey());

    expect($number)->toBe(sprintf(
        '%04d/EL-PNL/%s/%d', 1, RomanNumerals::month(now()->month), now()->year,
    ));
});

it('keeps the acceptance report format', function (): void {
    expect(AcceptanceReport::generateReportNumber($this->team->getKey()))
        ->toBe(sprintf('AR-%d-%04d', now()->year, 1));
});

it('never issues the same QE number twice across many allocations', function (): void {
    $teamId = $this->team->getKey();

    $numbers = collect(range(1, 30))
        ->map(fn (): string => QuotationEvaluation::generateQeNumber($teamId))
        ->all();

    expect(array_unique($numbers))->toHaveCount(30);
});

it('never issues the same PNL number twice across many allocations', function (): void {
    $teamId = $this->team->getKey();

    $numbers = collect(range(1, 30))
        ->map(fn (): string => ProfitAndLoss::generatePnlNumber($teamId))
        ->all();

    expect(array_unique($numbers))->toHaveCount(30);
});

it('never issues the same acceptance report number twice across many allocations', function (): void {
    $teamId = $this->team->getKey();

    $numbers = collect(range(1, 30))
        ->map(fn (): string => AcceptanceReport::generateReportNumber($teamId))
        ->all();

    expect(array_unique($numbers))->toHaveCount(30);
});
```

Confirm `App\Support\RomanNumerals` is the correct FQCN before running — check with
`grep -rn "class RomanNumerals" app/`. If it lives elsewhere, fix the import.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/SpecialFormatNumberTest.php`
Expected: the three format tests PASS; the three uniqueness tests FAIL with 1 unique
value instead of 30, because nothing is persisted between calls.

- [ ] **Step 3: Rewrite `QuotationEvaluation::generateQeNumber()`**

```php
    /**
     * Generate a unique QE number for the given team.
     * Format: {increment}-DS/QE/{roman_month}/{year}
     *
     * The increment is a per-team, per-year counter row. The previous
     * implementation read the last-inserted row of the year and re-derived the
     * increment from it, which restarted the count whenever a row was inserted
     * out of creation order.
     */
    public static function generateQeNumber(int $teamId): string
    {
        $year = now()->year;
        $month = now()->month;

        $increment = app(DocumentNumberAllocator::class)
            ->next($teamId, 'quotation_evaluation', (string) $year);

        return sprintf('%03d-DS/QE/%s/%d', $increment, RomanNumerals::month($month), $year);
    }
```

Add `use App\Services\Erp\Numbering\DocumentNumberAllocator;` to the imports.

- [ ] **Step 4: Rewrite `ProfitAndLoss::generatePnlNumber()`**

```php
    /**
     * Generate a unique PNL number for the given team.
     * Format: {4digit increment}/EL-PNL/{roman_month}/{year}
     *
     * @see \App\Models\QuotationEvaluation::generateQeNumber() for why this is a
     *      counter row.
     */
    public static function generatePnlNumber(int $teamId): string
    {
        $year = now()->year;
        $month = now()->month;

        $increment = app(DocumentNumberAllocator::class)
            ->next($teamId, 'profit_and_loss', (string) $year);

        return sprintf('%04d/EL-PNL/%s/%d', $increment, RomanNumerals::month($month), $year);
    }
```

Add `use App\Services\Erp\Numbering\DocumentNumberAllocator;` to the imports.

- [ ] **Step 5: Rewrite `AcceptanceReport::generateReportNumber()`**

```php
    /**
     * Generate a unique report number for the given team, scoped to the current year.
     * Format: AR-{year}-{increment}
     *
     * Previously this plucked every report number for the team and year into PHP
     * to compute a max — correct, but O(rows) in memory and still raceable.
     */
    public static function generateReportNumber(int $teamId): string
    {
        $year = now()->year;

        $sequence = app(DocumentNumberAllocator::class)
            ->next($teamId, 'acceptance_report', (string) $year);

        return sprintf('AR-%d-%04d', $year, $sequence);
    }
```

Add `use App\Services\Erp\Numbering\DocumentNumberAllocator;` to the imports.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/SpecialFormatNumberTest.php`
Expected: all six tests PASS.

- [ ] **Step 7: Verify every read-max generator is gone**

Run: `grep -rn "orderByDesc('.*_number')\|orderByDesc('id')" app/Models app/Observers`
Expected: no hits on a number-generation path. Any remaining hit is a generator this
plan missed — add it to the inventory table and to `BackfillDocumentNumberSequences::SOURCES`.

- [ ] **Step 8: Run the full suite with coverage gates**

```bash
php vendor/bin/pest --parallel
php vendor/bin/pest --type-coverage --min=99.9
php vendor/bin/phpstan analyse
php vendor/bin/pest --filter=arch
```
Expected: all PASS.

- [ ] **Step 9: Lint and commit**

```bash
php vendor/bin/rector process app/Models/QuotationEvaluation.php app/Models/ProfitAndLoss.php app/Models/AcceptanceReport.php tests/Feature/Erp/Numbering/SpecialFormatNumberTest.php
php vendor/bin/pint --dirty
git add app/Models/QuotationEvaluation.php app/Models/ProfitAndLoss.php app/Models/AcceptanceReport.php tests/Feature/Erp/Numbering/SpecialFormatNumberTest.php
git commit -m "fix: allocate QE, P&L and acceptance report numbers from the counter row"
```

---

## Deployment sequence

Order matters — deploying the code before seeding the counters makes every create
collide with history and fail on the unique index.

1. Deploy the migration from Task 1 (creates the empty table; no behaviour change).
2. Run `php artisan erp:backfill-document-sequences --dry-run` and eyeball the output.
3. Run `php artisan erp:backfill-document-sequences`.
4. Deploy Tasks 3–6.

If step 4 must roll back, the old read-max code works unchanged against the data — the
sequence table is inert when nothing reads it. Rolling *forward* again requires
re-running the backfill, since numbers issued by the old code after the seed will be
above the counter.

---

### Task 7: Assign buyer invoice numbers at issue, not at create

**Decided 2026-07-29.** The *Decision required before Task 3* gate was answered: gaps
should be avoided where practical. Assigning at create means every discarded draft burns
a number permanently. Assigning at issue means a draft carries no number at all, so
discarding one costs nothing and a gap can only arise if the issue transaction itself
fails after taking the counter.

For context, since the comparison came up: neither Magento nor SAP is gapless by
default. Magento's `increment_id` comes from a `sequence_order_*` auto-increment and a
failed placement burns the number; SAP's buffered number ranges are documented as
producing gaps. What both guarantee — and what this task delivers — is that **a number
is never reused and an issued document is never deleted.** The latter is enforced by
`RESTRICT` (referential-integrity plan, Task 2) plus the model's existing soft deletes.

`markAsSent()` (`app/Models/BuyerInvoice.php:286`) is the single issue point:
`issueFromOrder()` creates the invoice as `DRAFT`, builds its items, recalculates
totals, then calls `markAsSent()`. Assigning there covers every path.

**Files:**
- Create: `database/migrations/2026_07_29_130000_make_buyer_invoice_number_nullable.php`
- Modify: `app/Observers/BuyerInvoiceObserver.php:32-38` (remove creating-time assignment)
- Modify: `app/Models/BuyerInvoice.php` — `markAsSent()`, plus a new `assignNumberIfMissing()`
- Test: `tests/Feature/Erp/Numbering/InvoiceNumberAtIssueTest.php`

**Interfaces:**
- Consumes: `DocumentNumberAllocator::next(int $teamId, string $key, string $period): int`,
  and `BuyerInvoice::generateNextNumber(int $teamId): string` from Task 3
- Produces:
  ```php
  // App\Models\BuyerInvoice
  public function assignNumberIfMissing(): void;   // idempotent; no-op when a number exists
  ```
  `buyer_invoices.invoice_number` becomes **nullable**. A draft invoice has
  `invoice_number === null`. The `unique(['team_id','invoice_number'])` index is kept:
  PostgreSQL treats NULLs as distinct, so any number of unnumbered drafts coexist.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/Numbering/InvoiceNumberAtIssueTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\BuyerInvoice;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

function draftInvoice(): BuyerInvoice
{
    return BuyerInvoice::factory()
        ->recycle(test()->team)
        ->recycle(test()->currency)
        ->for(test()->request)
        ->create(['status' => InvoiceStatus::DRAFT]);
}

it('leaves a draft invoice unnumbered', function (): void {
    expect(draftInvoice()->invoice_number)->toBeNull();
});

it('allows many unnumbered drafts in one team', function (): void {
    draftInvoice();
    draftInvoice();
    draftInvoice();

    expect(BuyerInvoice::whereNull('invoice_number')->count())->toBe(3);
});

it('assigns a number when the invoice is issued', function (): void {
    $invoice = draftInvoice();
    $invoice->markAsSent();

    expect($invoice->refresh()->invoice_number)->toMatch('/^INV-\d{4}-\d{4}$/');
});

it('does not burn a number on a discarded draft', function (): void {
    $discarded = draftInvoice();
    $discarded->delete();

    $issued = draftInvoice();
    $issued->markAsSent();

    expect($issued->refresh()->invoice_number)->toBe('INV-'.date('Y').'-0001');
});

it('is idempotent — re-issuing does not renumber', function (): void {
    $invoice = draftInvoice();
    $invoice->markAsSent();
    $first = $invoice->refresh()->invoice_number;

    $invoice->assignNumberIfMissing();

    expect($invoice->refresh()->invoice_number)->toBe($first);
});

it('numbers consecutive issued invoices without gaps', function (): void {
    $numbers = [];
    foreach (range(1, 5) as $ignored) {
        $invoice = draftInvoice();
        $invoice->markAsSent();
        $numbers[] = $invoice->refresh()->invoice_number;
    }

    $year = date('Y');
    expect($numbers)->toBe([
        "INV-{$year}-0001", "INV-{$year}-0002", "INV-{$year}-0003",
        "INV-{$year}-0004", "INV-{$year}-0005",
    ]);
});

it('numbers an invoice issued straight from an order', function (): void {
    $invoice = draftInvoice();
    $invoice->markAsSent();

    expect($invoice->refresh()->invoice_number)->not->toBeNull()
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::SENT);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/InvoiceNumberAtIssueTest.php`
Expected: "leaves a draft invoice unnumbered" FAILS — the observer numbers it at create.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_29_130000_make_buyer_invoice_number_nullable.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A draft invoice carries no number; the counter is consumed at issue. That
 * makes a discarded draft cost nothing, so gaps arise only if the issue
 * transaction itself fails.
 *
 * The unique(team_id, invoice_number) index is deliberately kept: PostgreSQL
 * treats NULLs as distinct, so any number of unnumbered drafts coexist while
 * issued numbers stay unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_invoices', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('buyer_invoices', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable(false)->change();
        });
    }
};
```

The `down()` will fail if any unnumbered draft exists at rollback time. That is correct
— rolling back would otherwise have to invent numbers. If a rollback is genuinely
needed, issue or delete the drafts first.

- [ ] **Step 4: Stop numbering at create**

In `app/Observers/BuyerInvoiceObserver.php`, delete the whole auto-generate block at the
end of `creating()`:

```php
        // Auto-generate invoice number if not provided
        /** @var string|null $invoiceNumber */
        $invoiceNumber = $buyerInvoice->invoice_number;
        if (($invoiceNumber === null || $invoiceNumber === '') && $buyerInvoice->team_id !== null) {
            $buyerInvoice->invoice_number = BuyerInvoice::generateNextNumber($buyerInvoice->team_id);
        }
```

Leave the `team_id` / `creator_id` block above it untouched. Remove the now-unused
`use App\Models\BuyerInvoice;` import only if nothing else in the file references it.

- [ ] **Step 5: Assign at issue**

In `app/Models/BuyerInvoice.php`, add:

```php
    /**
     * Take an invoice number from the counter, unless one is already held.
     *
     * Idempotent by design: re-issuing, or any second call, must never
     * renumber a document that has already gone out to a buyer.
     */
    public function assignNumberIfMissing(): void
    {
        $number = $this->invoice_number;

        if ($number !== null && $number !== '') {
            return;
        }

        if ($this->team_id === null) {
            throw new \InvalidArgumentException('Cannot number an invoice with no team.');
        }

        $this->invoice_number = self::generateNextNumber($this->team_id);
    }
```

Then in `markAsSent()`, assign before the status changes, so a failed transition cannot
consume a number:

```php
    public function markAsSent(): void
    {
        if (! $this->status->canTransitionTo(InvoiceStatus::SENT)) {
            throw new \InvalidArgumentException('Cannot transition to sent from current status.');
        }

        // Numbering happens here, not at create: a draft that is never issued
        // must not consume a number. Assigned after the transition guard so a
        // rejected transition cannot burn one.
        $this->assignNumberIfMissing();

        $this->status = InvoiceStatus::SENT;

        if ($this->issued_at === null) {
            $this->issued_at = now();
        }
```

Leave the rest of the method (due-date calculation, `save()`) exactly as it is.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php vendor/bin/pest tests/Feature/Erp/Numbering/InvoiceNumberAtIssueTest.php`
Expected: all seven tests PASS.

- [ ] **Step 7: Find every surface that assumes a number exists**

An unnumbered draft is new to the system. Check each consumer:

```bash
grep -rn "invoice_number" app/Filament app/Services resources/views app/Mail
```

For each hit, decide: a Filament column or a Blade view showing `invoice_number` on a
draft will now render empty. Add a placeholder where that reads badly — Filament's
`->placeholder('Not yet issued')` on the column or entry is the idiomatic fix. Do not
add a fallback that invents a number.

`BuyerInvoice::getDisplayText()` at `:517` interpolates `$this->invoice_number` — give
it a `?? 'Draft'` so the label is not a bare separator.

- [ ] **Step 8: Run the full suite**

Run: `php vendor/bin/pest --parallel`
Expected: PASS. Tests that asserted a number on a freshly-created invoice must change to
assert `null` before issue and a number after — that is the behaviour change, not a
regression.

- [ ] **Step 9: Lint, analyse, commit**

```bash
php vendor/bin/rector process app/Models/BuyerInvoice.php app/Observers/BuyerInvoiceObserver.php database/migrations/2026_07_29_130000_make_buyer_invoice_number_nullable.php tests/Feature/Erp/Numbering/InvoiceNumberAtIssueTest.php
php vendor/bin/pint --dirty
php vendor/bin/phpstan analyse
php vendor/bin/pest --filter=arch
git add -A
git commit -m "feat: assign buyer invoice numbers at issue so discarded drafts leave no gap"
```

## Known follow-ups

Not addressed by this plan — do not change any index here; each is a schema change
with data implications, and all three are pre-existing (not a regression of this work):

- `supplier_quotes.quote_number` is unique globally rather than scoped to
  `(team_id, quote_number)`, unlike the other document tables (see
  `database/migrations/2026_07_05_130000_scope_document_number_uniqueness_to_team.php`,
  which scoped `supplier_orders`, `buyer_orders`, `buyer_quotes` and
  `supplier_invoices` but not this one). Two teams' first supplier quotes can
  collide.
- `shipments.shipment_number` is globally unique for the same reason.
- `supplier_payments.payment_number` is globally unique for the same reason.

Fixing any of these needs a migration to drop the global unique index, add the
`(team_id, ...)` composite index, and backfill/verify no existing cross-team
collision already exists in production data — out of scope here.

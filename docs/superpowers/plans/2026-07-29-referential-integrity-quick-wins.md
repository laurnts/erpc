# Referential Integrity Quick Wins Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop financial documents from being hard-deleted by FK cascade, and fix the stale agent-context file that tells every AI session this is a CRM.

**Architecture:** Two unrelated, independently revertible changes, grouped only because both are small and carry no design risk. Task 1 is a documentation rewrite. Task 2 replaces `ON DELETE CASCADE` with `ON DELETE RESTRICT` on the foreign keys that point *at* business entities from financial documents, following the rule from `/Users/laurnts/Sites/pos/ARCHITECTURE.md` §3 rule 4: referenced entities are disabled or archived, never hard-deleted.

**Tech Stack:** Laravel 12, PostgreSQL 15+, Pest 4.

## Global Constraints

- All PHP files declare `declare(strict_types=1);`
- All classes are `final` by default
- Comparisons use `===` / `!==` exclusively
- All tooling runs through the Docker wrapper: `php vendor/bin/<tool>`, never bare `pint`/`pest`/`phpstan`
- Before finalizing any change: `php vendor/bin/rector process <changed files>` then `php vendor/bin/pint --dirty`
- `team_id` cascades are **kept** — deleting a team is an intentional account closure and must remove its data

---

### Task 1: Correct the agent-context project file

`openspec/project.md` currently describes Relaticle CRM (the upstream fork base) — "next-generation open-source CRM", core entities "Companies, People, Opportunities, Tasks, Notes". It never mentions deals, suppliers, quotes, margin, or credit. It is the first file agents read for domain context, so every session starts from the wrong domain model.

**Files:**
- Modify: `openspec/project.md` (whole `## Purpose` and `## Domain Context` sections)

**Interfaces:**
- Consumes: nothing
- Produces: nothing (documentation only)

- [ ] **Step 1: Replace the `## Purpose` section**

Replace the existing `## Purpose` block with:

```markdown
## Purpose
ERPC is a deal lifecycle platform for back-to-back B2B trading — quote-to-cash on the
customer side, source-to-pay on the supplier side, joined in the middle by margin and
profit-and-loss tracking per deal. In one line: an ERP for a stockless trading
intermediary.

The system of record is the **deal** (a `Request`), not a product catalog and not an
order. Every customer-facing document has a supplier-facing mirror, and the business
value is the spread between them:

| Demand side (customer) | | Supply side (supplier) |
|---|---|---|
| Request | → sourcing → | Supplier quote request |
| Buyer Quote | ← evaluation ← | Supplier Quote |
| Buyer Order | back-to-back | Supplier Order |
| Buyer Invoice | | Supplier Invoice |
| Buyer Payment | deal P&L | Supplier Payment |

Forked from Relaticle CRM; the CRM base (companies, people, custom fields, teams)
remains underneath and is legacy surface, not the product.
```

- [ ] **Step 2: Replace the `## Domain Context` section**

Replace the existing `## Domain Context` block with:

```markdown
## Domain Context
- **Core entity:** `Request` — the deal. Everything else hangs off it.
- **Demand side:** `Request` → `RequestItem` → `BuyerQuote` → `BuyerOrder` →
  `BuyerInvoice` → `BuyerPayment`
- **Supply side:** `SupplierQuote` (collected per request) → `QuotationEvaluation`
  (bid comparison, sell-price construction) → `SupplierOrder` → `SupplierInvoice` →
  `SupplierPayment`
- **Fulfillment:** `Shipment`, `GoodsReceiveBatch`, `AcceptanceReport`; items within
  one deal may be fulfilled through different routes (mixed deals)
- **Economics:** `ProfitAndLoss` per deal — operational deal economics, not
  bookkeeping. Statutory accounting is deliberately out of scope and lives in
  dedicated software fed by exports.
- **Working capital:** buyer credit limits with approval workflows
  (`BuyerCreditLimitRequest`), `BuyerCreditUsageHistory` ledger, prepayment/balance
  invoice splitting, multi-currency with `ExchangeRate`.
- **Multi-Tenancy:** team-based isolation (`HasTeam`, `HasCreator` traits) with
  memberships and invitations.
- **Portals:** three panels — internal team (`app`), buyer portal, supplier portal.
  Tailored counterparty experience is a deliberate competitive surface.
- **CRM base (legacy):** Companies, People, Opportunities, Tasks, Notes, custom
  fields. Companies are dual-purpose: a company is a buyer, a supplier, or both.
```

- [ ] **Step 3: Verify nothing else in the file still says CRM**

Run: `grep -in "crm\|opportunit\|relaticle" openspec/project.md`
Expected: only the two intentional mentions — "Forked from Relaticle CRM" in Purpose and "CRM base (legacy)" in Domain Context. Any other hit is a leftover; fix it.

- [ ] **Step 4: Validate OpenSpec still parses**

Run: `openspec validate --all`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add openspec/project.md
git commit -m "docs: correct project.md domain context from Relaticle CRM to ERPC trading domain"
```

---

### Task 2: RESTRICT deletes of entities referenced by financial documents

Today `buyer_orders`, `buyer_invoices`, `supplier_invoices`, `buyer_payments`,
`supplier_payments` and `profit_and_losses` cascade-delete from `requests` and
`companies`. Deleting one request destroys its orders, invoices and P&L at the
database level, past the models' soft deletes. Payments cascade from their invoice.

The eight FKs converted here are exactly the ones where the child is a financial
record and the parent is a business entity that should be archived instead. `team_id`
FKs are untouched.

**Files:**
- Create: `database/migrations/2026_07_29_100000_restrict_deletes_on_financial_documents.php`
- Create: `tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: nothing in PHP. Produces a database guarantee later tasks rely on:
  a `requests` or `companies` row referenced by any financial document cannot be
  hard-deleted; the attempt raises `Illuminate\Database\QueryException`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\BuyerInvoice;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
});

it('refuses to hard-delete a request that has a buyer invoice', function (): void {
    BuyerInvoice::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create();

    expect(fn (): mixed => Request::withTrashed()
        ->whereKey($this->request->getKey())
        ->forceDelete())
        ->toThrow(QueryException::class);

    expect(Request::withTrashed()->whereKey($this->request->getKey())->exists())->toBeTrue();
});

it('refuses to hard-delete a company that has a buyer invoice through its request', function (): void {
    BuyerInvoice::factory()
        ->recycle($this->team)
        ->recycle($this->currency)
        ->for($this->request)
        ->create();

    expect(fn (): mixed => Company::withTrashed()
        ->whereKey($this->buyer->getKey())
        ->forceDelete())
        ->toThrow(QueryException::class);
});

it('still allows hard-deleting a request with no financial documents', function (): void {
    $orphan = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();

    Request::withTrashed()->whereKey($orphan->getKey())->forceDelete();

    expect(Request::withTrashed()->whereKey($orphan->getKey())->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/bin/pest tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php`
Expected: the first two tests FAIL — no exception is thrown, because the cascade
succeeds and silently removes the invoice. The third test passes already.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_29_100000_restrict_deletes_on_financial_documents.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial documents must outlive the entities they reference. A request or a
 * company is archived (soft-deleted / disabled), never hard-deleted, once it has
 * produced an order, invoice, payment or P&L. RESTRICT makes that the enforced
 * default instead of a convention. team_id cascades are deliberately untouched:
 * deleting a team is account closure and must remove its data.
 *
 * @see /Users/laurnts/Sites/pos/ARCHITECTURE.md §3 rule 4
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, references: string}>
     */
    private array $constraints = [
        ['table' => 'buyer_orders', 'column' => 'request_id', 'references' => 'requests'],
        ['table' => 'buyer_orders', 'column' => 'buyer_id', 'references' => 'companies'],
        ['table' => 'buyer_invoices', 'column' => 'request_id', 'references' => 'requests'],
        ['table' => 'buyer_payments', 'column' => 'buyer_invoice_id', 'references' => 'buyer_invoices'],
        ['table' => 'supplier_invoices', 'column' => 'request_id', 'references' => 'requests'],
        ['table' => 'supplier_invoices', 'column' => 'supplier_id', 'references' => 'companies'],
        ['table' => 'supplier_payments', 'column' => 'supplier_invoice_id', 'references' => 'supplier_invoices'],
        ['table' => 'profit_and_losses', 'column' => 'request_id', 'references' => 'requests'],
    ];

    public function up(): void
    {
        foreach ($this->constraints as $constraint) {
            Schema::table($constraint['table'], function (Blueprint $table) use ($constraint): void {
                $table->dropForeign([$constraint['column']]);
                $table->foreign($constraint['column'])
                    ->references('id')
                    ->on($constraint['references'])
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->constraints as $constraint) {
            Schema::table($constraint['table'], function (Blueprint $table) use ($constraint): void {
                $table->dropForeign([$constraint['column']]);
                $table->foreign($constraint['column'])
                    ->references('id')
                    ->on($constraint['references'])
                    ->cascadeOnDelete();
            });
        }
    }
};
```

- [ ] **Step 4: Run the migration against the test database and run the test**

Run: `php vendor/bin/pest tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php`
Expected: all three tests PASS.

If a test errors with `there is no unique constraint matching given keys` or a
constraint-name mismatch, the FK name deviates from Laravel's
`<table>_<column>_foreign` convention. Inspect with:
`php artisan db:table buyer_orders` and pass the literal name to `dropForeign('...')`.

- [ ] **Step 5: Run the full suite to find tests that relied on cascade cleanup**

Run: `php vendor/bin/pest --parallel`
Expected: PASS. If a test fails with a foreign-key-violation `QueryException` during
teardown or factory cleanup, that test was hard-deleting a parent that owns financial
rows. Fix the *test*, not the migration — delete the children first, or soft-delete the
parent. Do not weaken the constraint to make a test pass.

- [ ] **Step 6: Lint the changed files**

```bash
php vendor/bin/rector process database/migrations/2026_07_29_100000_restrict_deletes_on_financial_documents.php tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php
php vendor/bin/pint --dirty
```
Expected: no remaining diffs reported on a second run.

- [ ] **Step 7: Verify static analysis and architecture gates**

```bash
php vendor/bin/phpstan analyse
php vendor/bin/pest --filter=arch
```
Expected: both PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_29_100000_restrict_deletes_on_financial_documents.php tests/Feature/Erp/FinancialDocumentDeleteProtectionTest.php
git commit -m "fix: RESTRICT deletes of requests, companies and invoices referenced by financial documents"
```

---

## Deployment note

Task 2's migration is expand-only in the sense that it adds no columns, but it **can
fail on a production database that already contains orphan-producing deletes in
flight**. Before deploying, confirm no `requests` or `companies` rows are queued for
hard deletion. The migration itself is transactional under PostgreSQL — it either fully
applies or fully rolls back.

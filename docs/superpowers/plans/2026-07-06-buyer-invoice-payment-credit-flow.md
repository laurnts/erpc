# Buyer Invoice, Payment & Credit Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the dormant `BuyerInvoice`/`BuyerPayment` models into the Request "Invoices" tab so staff can issue a real invoice (with a termin/due date) from a confirmed order and record payments, and make recording a payment release the buyer's reserved credit.

**Architecture:** Keep the existing credit gate at `BuyerOrder::confirm()` (credit reserved when the order is confirmed). Add a `credit_released` ledger column on `buyer_orders` that tracks how much of that reservation has already been unwound by payments, so payment-release and cancel-restore never double-count. Issuing an invoice copies the confirmed order into a `BuyerInvoice` (status `SENT`, `due_at = issued_at + net_days`) using the existing `BuyerInvoiceItem::createFromOrderItem()`. Recording a `BuyerPayment` drives `BuyerInvoice::updatePaymentStatus()` (already implemented) and, via `BuyerPaymentObserver`, reconciles released credit against the invoice's `amount_paid`.

**Tech Stack:** Laravel 12, Filament 5 (relation-manager record actions), Pest 4, PHP 8.4.

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`.
- Default all classes to `final`; Observers/Services are `final readonly`.
- All methods have explicit return types; use `===`/`!==` only.
- Money math uses `(float)` casts on decimal string columns; buyer credit columns (`available_credit`, `credit_used`) are `decimal:2`; invoice money columns are `decimal:4`.
- Credit mutations on `Company` MUST run inside a `DB::transaction` with `$buyer->lockForUpdate(); $buyer->refresh();` and write a `BuyerCreditUsageHistory` row — mirror the existing `BuyerOrder::confirm()` / `restoreCredit()`.
- Buyer credit lives on `Company` (`credit_limit`, `available_credit`, `credit_used`, `credit_status`). The buyer of a `BuyerOrder` is `$order->buyer` (a `Company`).
- Run `vendor/bin/pint` before each commit. Run tests with `php artisan test --compact --filter=...`.
- v1 scope: one active (non-cancelled, non-credit-note) `BuyerInvoice` per order; single `due_at`; partial payments allowed. No multi-milestone termin.

---

### Task 1: Add `credit_released` column to `buyer_orders`

**Files:**
- Create: `database/migrations/2026_07_06_100000_add_credit_released_to_buyer_orders_table.php`
- Modify: `app/Models/BuyerOrder.php` (add `credit_released` to `$fillable`, `$attributes`, `casts()`, and the `@property` docblock)

**Interfaces:**
- Produces: `buyer_orders.credit_released` (decimal 15,2, default `0`), exposed as `BuyerOrder->credit_released` (cast `decimal:2`).

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->decimal('credit_released', 15, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->dropColumn('credit_released');
        });
    }
};
```

- [ ] **Step 2: Add the column to the model**

In `app/Models/BuyerOrder.php`, add `'credit_released'` to `$fillable` (after `'total'`), add `'credit_released' => '0.00'` to `$attributes` (after the `'total' => '0.00'` line), add `'credit_released' => 'decimal:2'` to `casts()` (after the `'total'` cast), and add `* @property string $credit_released` to the docblock after the `$total` line.

- [ ] **Step 3: Run the migration and verify**

Run: `php artisan migrate --no-interaction`
Expected: migration runs; `buyer_orders` has a `credit_released` column.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_06_100000_add_credit_released_to_buyer_orders_table.php app/Models/BuyerOrder.php
git commit -m "Add credit_released tracking column to buyer_orders"
```

---

### Task 2: Reservation accounting on `BuyerOrder`

Add the release/re-reserve reconciliation and update `restoreCredit()` so cancel restores only the still-reserved remainder.

**Files:**
- Modify: `app/Models/BuyerOrder.php`
- Test: `tests/Feature/Erp/BuyerOrderCreditReleaseTest.php` (create)

**Interfaces:**
- Consumes: `BuyerOrder::confirm()` (writes the `debit` `BuyerCreditUsageHistory` row that marks the order as credit-reserved), `BuyerInvoice->amount_paid` (string, `decimal:4`), `BuyerInvoice->total`.
- Produces:
  - `BuyerOrder::hasReservedCredit(): bool` — true iff a `debit` credit-history row exists for this order.
  - `BuyerOrder::reconcileReleasedCreditFor(BuyerInvoice $invoice): void` — makes `credit_released` equal `min($invoice->amount_paid, $this->total)`, moving buyer credit by the delta.
  - `BuyerOrder::restoreCredit(): void` — now restores `max(0, total − credit_released)` and sets `credit_released = total`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/BuyerOrderCreditReleaseTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\BuyerCreditUsageHistory;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->actingAs($this->user);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'credit_status' => true,
        'credit_limit' => '1000.00',
        'available_credit' => '1000.00',
        'credit_used' => '0.00',
    ]);

    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function creditReleaseOrder(Tests\TestCase $test, array $attributes = []): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle($test->team)
        ->recycle($test->user)
        ->forRequest($test->request)
        ->forBuyer($test->buyer)
        ->create(array_merge(['status' => OrderStatus::DRAFT, 'total' => '400.00'], $attributes));
}

function creditReleaseInvoice(Tests\TestCase $test, BuyerOrder $order, string $amountPaid): BuyerInvoice
{
    return BuyerInvoice::factory()
        ->recycle($test->team)
        ->forRequest($test->request)
        ->withCurrency($test->currency)
        ->forBuyerOrder($order)
        ->sent()
        ->withTotals(400, 0, 400)
        ->create(['amount_paid' => $amountPaid]);
}

it('releases credit up to the amount paid on the invoice', function (): void {
    $order = creditReleaseOrder($this);
    $order->confirm();

    $this->buyer->refresh();
    expect((float) $this->buyer->available_credit)->toBe(600.00);

    $invoice = creditReleaseInvoice($this, $order, '150.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $this->buyer->refresh();
    $order->refresh();

    expect((float) $this->buyer->available_credit)->toBe(750.00)
        ->and((float) $this->buyer->credit_used)->toBe(250.00)
        ->and((float) $order->credit_released)->toBe(150.00)
        ->and(
            BuyerCreditUsageHistory::query()
                ->where('related_type', BuyerOrder::class)
                ->where('related_id', $order->getKey())
                ->where('transaction_type', 'credit')
                ->exists()
        )->toBeTrue();
});

it('caps release at the order total on overpayment', function (): void {
    $order = creditReleaseOrder($this);
    $order->confirm();

    $invoice = creditReleaseInvoice($this, $order, '500.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $this->buyer->refresh();
    $order->refresh();

    expect((float) $this->buyer->available_credit)->toBe(1000.00)
        ->and((float) $this->buyer->credit_used)->toBe(0.00)
        ->and((float) $order->credit_released)->toBe(400.00);
});

it('does not release credit for an order that never reserved it', function (): void {
    $this->buyer->update(['credit_status' => false]);

    $order = creditReleaseOrder($this);
    $order->confirm(); // credit_status false → no reservation, no debit row

    $invoice = creditReleaseInvoice($this, $order, '400.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $this->buyer->refresh();
    $order->refresh();

    expect((float) $this->buyer->available_credit)->toBe(1000.00)
        ->and((float) $order->credit_released)->toBe(0.00);
});

it('restores only the unreleased remainder when cancelling after a partial payment', function (): void {
    $order = creditReleaseOrder($this);
    $order->confirm();

    $invoice = creditReleaseInvoice($this, $order, '150.0000');
    $order->reconcileReleasedCreditFor($invoice);

    $order->refresh();
    $order->cancel();

    $this->buyer->refresh();

    expect((float) $this->buyer->available_credit)->toBe(1000.00)
        ->and((float) $this->buyer->credit_used)->toBe(0.00)
        ->and($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Erp/BuyerOrderCreditReleaseTest.php`
Expected: FAIL — `Call to undefined method App\Models\BuyerOrder::reconcileReleasedCreditFor()`.

- [ ] **Step 3: Implement `hasReservedCredit()` and `reconcileReleasedCreditFor()`**

In `app/Models/BuyerOrder.php`, add these methods (after `restoreCredit()`):

```php
/**
 * Whether this order actually reserved buyer credit at confirmation
 * (a debit credit-history row exists for it).
 */
public function hasReservedCredit(): bool
{
    return BuyerCreditUsageHistory::query()
        ->where('related_type', self::class)
        ->where('related_id', $this->getKey())
        ->where('transaction_type', 'debit')
        ->exists();
}

/**
 * Reconcile released credit so credit_released == min(invoice.amount_paid, total).
 * Releases credit when payments arrive; re-reserves if a payment is reversed.
 */
public function reconcileReleasedCreditFor(BuyerInvoice $invoice): void
{
    if (! $this->hasReservedCredit()) {
        return;
    }

    $buyer = $this->buyer;
    if ($buyer === null) {
        return;
    }

    $orderTotal = (float) $this->total;
    $paid = max(0.0, (float) $invoice->amount_paid);
    $target = min($paid, $orderTotal);
    $current = (float) $this->credit_released;
    $delta = round($target - $current, 2);

    if (abs($delta) < 0.005) {
        return;
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($buyer, $delta, $target): void {
        $buyer->lockForUpdate();
        $buyer->refresh();

        $availableBefore = (float) $buyer->available_credit;
        $usedBefore = (float) $buyer->credit_used;

        if ($delta > 0) {
            // Release credit back to the buyer.
            $buyer->available_credit = $availableBefore + $delta;
            $buyer->credit_used = max(0, $usedBefore - $delta);
            $transactionType = 'credit';
            $description = "Order {$this->order_number} payment received - credit released";
        } else {
            // Payment reversed: re-reserve credit.
            $reReserve = abs($delta);
            $buyer->available_credit = max(0, $availableBefore - $reReserve);
            $buyer->credit_used = $usedBefore + $reReserve;
            $transactionType = 'debit';
            $description = "Order {$this->order_number} payment reversed - credit re-reserved";
        }

        $buyer->save();

        $this->credit_released = (string) $target;
        $this->saveQuietly();

        BuyerCreditUsageHistory::create([
            'team_id' => $buyer->team_id,
            'buyer_id' => $buyer->id,
            'transaction_type' => $transactionType,
            'amount' => abs($delta),
            'max_credit_limit_before' => 0,
            'max_credit_limit_after' => 0,
            'available_credit_before' => $availableBefore,
            'available_credit_after' => $buyer->available_credit,
            'credit_used_before' => $usedBefore,
            'credit_used_after' => $buyer->credit_used,
            'related_type' => self::class,
            'related_id' => $this->id,
            'description' => $description,
            'created_by_id' => auth()->id(),
        ]);
    });
}
```

- [ ] **Step 4: Update `restoreCredit()` to restore only the unreleased remainder**

In `app/Models/BuyerOrder.php`, in `restoreCredit()`, change the line
`$orderTotal = (float) $this->total;`
to:

```php
$orderTotal = max(0, (float) $this->total - (float) $this->credit_released);
```

and immediately after `$buyer->save();` inside the transaction closure, add:

```php
$this->credit_released = $this->total;
$this->saveQuietly();
```

(This guarantees a later reconcile/cancel cannot restore the same amount twice.)

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Erp/BuyerOrderCreditReleaseTest.php`
Expected: PASS (4 passed).

- [ ] **Step 6: Run the existing credit/cancel regression tests**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerOrderActionTest.php tests/Unit/Models/CompanyCreditLimitTest.php`
Expected: PASS (existing cancel behaviour unchanged — factory orders have `credit_released = 0`, so cancel still restores the full total).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app/Models/BuyerOrder.php tests/Feature/Erp/BuyerOrderCreditReleaseTest.php
git add app/Models/BuyerOrder.php tests/Feature/Erp/BuyerOrderCreditReleaseTest.php
git commit -m "Release buyer credit as invoices are paid; guard cancel double-restore"
```

---

### Task 3: Reconcile credit from the payment lifecycle

Make recording/deleting a `BuyerPayment` reconcile the linked order's released credit.

**Files:**
- Modify: `app/Observers/BuyerPaymentObserver.php`
- Test: `tests/Feature/Erp/BuyerPaymentCreditReleaseTest.php` (create)

**Interfaces:**
- Consumes: `BuyerInvoice::updatePaymentStatus()` (recomputes `amount_paid`), `BuyerOrder::reconcileReleasedCreditFor()` (Task 2).
- Produces: after any payment create/update/delete/restore, the linked order's credit is reconciled to the invoice's paid amount.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/BuyerPaymentCreditReleaseTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->actingAs($this->user);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'credit_status' => true,
        'credit_limit' => '1000.00',
        'available_credit' => '1000.00',
        'credit_used' => '0.00',
    ]);

    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();

    $this->order = BuyerOrder::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->forRequest($this->request)
        ->forBuyer($this->buyer)
        ->create(['status' => OrderStatus::DRAFT, 'total' => '400.00']);
    $this->order->confirm();

    $this->invoice = BuyerInvoice::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->withCurrency($this->currency)
        ->forBuyerOrder($this->order)
        ->sent()
        ->withTotals(400, 0, 400)
        ->create();
});

it('releases credit when a payment is recorded against the invoice', function (): void {
    BuyerPayment::factory()
        ->recycle($this->team)
        ->forBuyerInvoice($this->invoice)
        ->create([
            'amount' => '400.0000',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

    $this->buyer->refresh();

    expect((float) $this->buyer->available_credit)->toBe(1000.00)
        ->and((float) $this->buyer->credit_used)->toBe(0.00)
        ->and((float) $this->order->refresh()->credit_released)->toBe(400.00);
});

it('releases proportional credit on a partial payment', function (): void {
    BuyerPayment::factory()
        ->recycle($this->team)
        ->forBuyerInvoice($this->invoice)
        ->create([
            'amount' => '100.0000',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

    $this->buyer->refresh();

    expect((float) $this->buyer->available_credit)->toBe(700.00)
        ->and((float) $this->order->refresh()->credit_released)->toBe(100.00);
});

it('re-reserves credit when a payment is deleted', function (): void {
    $payment = BuyerPayment::factory()
        ->recycle($this->team)
        ->forBuyerInvoice($this->invoice)
        ->create([
            'amount' => '400.0000',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
        ]);

    $payment->delete();

    $this->buyer->refresh();

    expect((float) $this->buyer->available_credit)->toBe(600.00)
        ->and((float) $this->order->refresh()->credit_released)->toBe(0.00);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Erp/BuyerPaymentCreditReleaseTest.php`
Expected: FAIL — credit is not released (available_credit stays 600) because the observer does not reconcile yet.

- [ ] **Step 3: Add reconciliation to the observer**

In `app/Observers/BuyerPaymentObserver.php`, add a private helper and call it from each lifecycle method. Replace the body of `created`, `updated`, `deleted`, and `restored` so each calls the helper after `updatePaymentStatus()`:

```php
/**
 * Handle the BuyerPayment "created" event.
 */
public function created(BuyerPayment $buyerPayment): void
{
    $this->reconcile($buyerPayment);
}

/**
 * Handle the BuyerPayment "updated" event.
 */
public function updated(BuyerPayment $buyerPayment): void
{
    $this->reconcile($buyerPayment);
}

/**
 * Handle the BuyerPayment "deleted" event.
 */
public function deleted(BuyerPayment $buyerPayment): void
{
    $this->reconcile($buyerPayment);
}

/**
 * Handle the BuyerPayment "restored" event.
 */
public function restored(BuyerPayment $buyerPayment): void
{
    $this->reconcile($buyerPayment);
}

/**
 * Update the invoice payment status, then reconcile the order's released credit.
 */
private function reconcile(BuyerPayment $buyerPayment): void
{
    $invoice = $buyerPayment->buyerInvoice;
    $invoice->updatePaymentStatus();

    $order = $invoice->buyerOrder;
    if ($order !== null) {
        $order->reconcileReleasedCreditFor($invoice);
    }
}
```

Keep the `creating` method unchanged. Add `use App\Models\BuyerPayment;` is already present; no new imports needed.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Erp/BuyerPaymentCreditReleaseTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Observers/BuyerPaymentObserver.php tests/Feature/Erp/BuyerPaymentCreditReleaseTest.php
git add app/Observers/BuyerPaymentObserver.php tests/Feature/Erp/BuyerPaymentCreditReleaseTest.php
git commit -m "Reconcile buyer credit from the payment lifecycle"
```

---

### Task 4: `BuyerInvoice::issueFromOrder()` factory method

Create the invoice-from-order issuing logic as a static method so it is unit-testable and reusable by the UI.

**Files:**
- Modify: `app/Models/BuyerInvoice.php`
- Test: `tests/Feature/Erp/BuyerInvoiceIssueFromOrderTest.php` (create)

**Interfaces:**
- Consumes: `BuyerOrder` (with `items`, `buyerQuote`, `team`, `total`, `payment_terms_days`), `BuyerInvoiceItem::createFromOrderItem()` (exists), `Team::getBaseCurrency()` (exists), `BuyerInvoice::recalculateTotals()` (exists), `BuyerInvoice::markAsSent()` (exists).
- Produces: `BuyerInvoice::issueFromOrder(BuyerOrder $order): self` — returns a persisted `SENT` invoice with copied items, `issued_at = today`, `due_at = today + net_days`. Throws `InvalidArgumentException` if the order is not confirmed, already has an active invoice, or no currency can be resolved.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Erp/BuyerInvoiceIssueFromOrderTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
    $this->actingAs($this->user);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'credit_status' => true,
        'credit_limit' => '10000.00',
        'available_credit' => '10000.00',
        'credit_used' => '0.00',
    ]);
    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();

    $this->order = BuyerOrder::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->forRequest($this->request)
        ->forBuyer($this->buyer)
        ->create([
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
            'total' => '220.00',
            'payment_terms_days' => 14,
        ]);

    BuyerOrderItem::factory()
        ->recycle($this->team)
        ->create([
            'buyer_order_id' => $this->order->getKey(),
            'description' => 'Widget',
            'quantity' => '2.0000',
            'unit_price' => '100.0000',
            'tax_rate' => '10.0000',
            'is_tax_inclusive' => false,
            'sort_order' => 0,
        ]);
});

it('issues a sent invoice from a confirmed order with a termin', function (): void {
    $invoice = BuyerInvoice::issueFromOrder($this->order);

    expect($invoice->status)->toBe(InvoiceStatus::SENT)
        ->and($invoice->buyer_order_id)->toBe($this->order->getKey())
        ->and($invoice->request_id)->toBe($this->request->getKey())
        ->and($invoice->currency_id)->toBe($this->currency->getKey())
        ->and($invoice->net_days)->toBe(14)
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($invoice->due_at?->toDateString())->toBe($invoice->issued_at->copy()->addDays(14)->toDateString())
        ->and($invoice->items)->toHaveCount(1)
        ->and((float) $invoice->total)->toBe(220.0);
});

it('refuses to issue from an unconfirmed order', function (): void {
    $this->order->update(['status' => OrderStatus::DRAFT]);

    BuyerInvoice::issueFromOrder($this->order);
})->throws(InvalidArgumentException::class);

it('refuses to issue a second active invoice for the same order', function (): void {
    BuyerInvoice::issueFromOrder($this->order);

    BuyerInvoice::issueFromOrder($this->order->refresh());
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Erp/BuyerInvoiceIssueFromOrderTest.php`
Expected: FAIL — `Call to undefined method App\Models\BuyerInvoice::issueFromOrder()`.

- [ ] **Step 3: Implement `issueFromOrder()`**

In `app/Models/BuyerInvoice.php`, add `use App\Enums\OrderStatus;` to the imports, then add this static method (after `generateNextNumber()`):

```php
/**
 * Issue a sent invoice from a confirmed buyer order.
 */
public static function issueFromOrder(BuyerOrder $order): self
{
    if ($order->status !== OrderStatus::CONFIRMED) {
        throw new \InvalidArgumentException('Only confirmed orders can be invoiced.');
    }

    $existing = self::query()
        ->where('buyer_order_id', $order->getKey())
        ->where('type', InvoiceType::STANDARD)
        ->whereNot('status', InvoiceStatus::CANCELLED)
        ->exists();

    if ($existing) {
        throw new \InvalidArgumentException('This order already has an active invoice.');
    }

    $currencyId = $order->buyerQuote?->currency_id
        ?? $order->team?->getBaseCurrency()?->getKey();

    if ($currencyId === null) {
        throw new \InvalidArgumentException('No currency could be resolved for this invoice.');
    }

    $invoice = new self;
    $invoice->team_id = $order->team_id;
    /** @var int|null $creatorId */
    $creatorId = auth()->id();
    $invoice->creator_id = $creatorId;
    $invoice->request_id = $order->request_id;
    $invoice->buyer_order_id = $order->getKey();
    $invoice->type = InvoiceType::STANDARD;
    $invoice->status = InvoiceStatus::DRAFT;
    $invoice->currency_id = $currencyId;
    $invoice->net_days = $order->payment_terms_days;
    $invoice->save();

    $order->loadMissing('items');
    foreach ($order->items as $orderItem) {
        BuyerInvoiceItem::createFromOrderItem($invoice, $orderItem);
    }

    $invoice->recalculateTotals();
    $invoice->markAsSent();

    return $invoice->refresh();
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Erp/BuyerInvoiceIssueFromOrderTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Models/BuyerInvoice.php tests/Feature/Erp/BuyerInvoiceIssueFromOrderTest.php
git add app/Models/BuyerInvoice.php tests/Feature/Erp/BuyerInvoiceIssueFromOrderTest.php
git commit -m "Add BuyerInvoice::issueFromOrder() to invoice a confirmed order"
```

---

### Task 5: "Issue Invoice" action on the Invoices tab

Add a record action that issues the invoice and emails it to the buyer.

**Files:**
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php`
- Test: `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php` (create)

**Interfaces:**
- Consumes: `BuyerInvoice::issueFromOrder()` (Task 4), `InvoiceToBuyerMail` (constructor `__construct(public readonly BuyerInvoice $invoice)`), `EmailTemplateService::sendWithTeamSettings(Team $team, Mailable $mailable, string|array $to)`.
- Produces: an `issueInvoice` record action, visible when `status === OrderStatus::CONFIRMED` and the order has no active invoice.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Mail\Erp\InvoiceToBuyerMail;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    Currency::factory()->create([
        'code' => $this->team->getBaseCurrencyCode(),
        'is_active' => true,
    ]);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'email' => 'buyer@example.com',
        'credit_status' => true,
        'credit_limit' => '10000.00',
        'available_credit' => '10000.00',
        'credit_used' => '0.00',
    ]);
    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();
});

function invoiceActionOrder(Tests\TestCase $test, OrderStatus $status): BuyerOrder
{
    $order = BuyerOrder::factory()
        ->recycle($test->team)
        ->recycle($test->user)
        ->forRequest($test->request)
        ->forBuyer($test->buyer)
        ->create(['status' => $status, 'confirmed_at' => now(), 'total' => '110.00', 'payment_terms_days' => 30]);

    BuyerOrderItem::factory()->recycle($test->team)->create([
        'buyer_order_id' => $order->getKey(),
        'description' => 'Item',
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_rate' => '10.0000',
        'is_tax_inclusive' => false,
        'sort_order' => 0,
    ]);

    return $order;
}

function invoiceActionRelationManager(Tests\TestCase $test): Testable
{
    return livewire(BuyerOrdersRelationManager::class, [
        'ownerRecord' => $test->request,
        'pageClass' => ViewRequest::class,
    ]);
}

it('issues an invoice and emails it to the buyer', function (): void {
    Mail::fake();

    $order = invoiceActionOrder($this, OrderStatus::CONFIRMED);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionVisible(TestAction::make('issueInvoice')->table($order))
        ->callAction(TestAction::make('issueInvoice')->table($order))
        ->assertNotified('Invoice issued');

    $invoice = BuyerInvoice::query()->where('buyer_order_id', $order->getKey())->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::SENT);

    Mail::assertSent(
        InvoiceToBuyerMail::class,
        fn (InvoiceToBuyerMail $mail): bool => $mail->invoice->is($invoice)
            && $mail->hasTo('buyer@example.com')
    );
});

it('hides issueInvoice for a draft order', function (): void {
    $order = invoiceActionOrder($this, OrderStatus::DRAFT);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionHidden(TestAction::make('issueInvoice')->table($order));
});

it('hides issueInvoice once an invoice already exists', function (): void {
    $order = invoiceActionOrder($this, OrderStatus::CONFIRMED);
    BuyerInvoice::issueFromOrder($order);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionHidden(TestAction::make('issueInvoice')->table($order->refresh()));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`
Expected: FAIL — action `issueInvoice` does not exist (`assertActionVisible` fails).

- [ ] **Step 3: Add the `issueInvoice` action**

In `app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php`, add these imports near the other model/mail imports at the top of the file:

```php
use App\Mail\Erp\InvoiceToBuyerMail;
use App\Models\BuyerInvoice;
```

Then, inside the `ActionGroup::make([ ... ])` in `recordActions()`, add this action immediately after the `resend` action (before `Action::make('confirm')`):

```php
Action::make('issueInvoice')
    ->label('Issue Invoice')
    ->icon('heroicon-o-document-text')
    ->color('primary')
    ->visible(fn (?BuyerOrder $record): bool => $record !== null
        && $record->status === OrderStatus::CONFIRMED
        && ! BuyerInvoice::query()
            ->where('buyer_order_id', $record->getKey())
            ->where('type', \App\Enums\InvoiceType::STANDARD)
            ->whereNot('status', \App\Enums\InvoiceStatus::CANCELLED)
            ->exists())
    ->requiresConfirmation()
    ->modalHeading('Issue invoice to buyer?')
    ->modalDescription(function (BuyerOrder $record): string {
        $buyerEmail = $record->buyer->email ?? null;
        $description = 'This will create an invoice from the order and email it to the buyer.';

        if (empty($buyerEmail)) {
            $description .= "\n\n⚠️ **Warning:** The buyer has no email address configured. The invoice will be created, but no email will be sent.";
        } else {
            $description .= "\n\n📧 Invoice will be sent to: {$buyerEmail}";
        }

        return $description;
    })
    ->action(function (BuyerOrder $record): void {
        try {
            $invoice = BuyerInvoice::issueFromOrder($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Could not issue invoice')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $buyerEmail = $record->buyer->email ?? null;

        if (empty($buyerEmail)) {
            Notification::make()
                ->title('Invoice issued')
                ->body('Invoice has been created, but no email was sent because the buyer has no email address.')
                ->warning()
                ->send();

            return;
        }

        try {
            app(EmailTemplateService::class)->sendWithTeamSettings(
                $record->team,
                new InvoiceToBuyerMail($invoice),
                $buyerEmail,
            );

            Notification::make()
                ->title('Invoice issued')
                ->body("Invoice has been issued and sent to {$buyerEmail}.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Failed to send buyer invoice email', [
                'invoice_id' => $invoice->id,
                'buyer_email' => $buyerEmail,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Invoice issued (email failed)')
                ->body("Invoice was created, but the email to {$buyerEmail} could not be sent. Error: ".$e->getMessage())
                ->danger()
                ->send();
        }
    }),
```

(`Notification`, `Log`, `EmailTemplateService`, `Action`, and `OrderStatus` are already imported in this file — verify at the top; add any that are missing.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php
git add app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php
git commit -m "Add Issue Invoice action to the Invoices tab"
```

---

### Task 6: "Record Payment" action on the Invoices tab

Add a record action that records a `BuyerPayment` against the order's active invoice (which, via Task 3, releases credit).

**Files:**
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php`
- Test: append to `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`

**Interfaces:**
- Consumes: `BuyerPayment` model (fillable: `buyer_invoice_id`, `payment_method`, `amount`, `payment_date`, `reference_number`, `notes`), `PaymentMethod` enum, `InvoiceStatus::canRecordPayment()`.
- Produces: a `recordPayment` record action, visible when the order has an active invoice whose status `canRecordPayment()`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`. Add these imports to the top `use` block: `use App\Enums\PaymentMethod;` and `use App\Models\BuyerPayment;`. Then add:

```php
it('records a payment against the invoice and releases credit', function (): void {
    // Build the order in DRAFT and confirm() it, so credit is genuinely
    // reserved (a debit history row is written and hasReservedCredit() is true).
    $order = invoiceActionOrder($this, OrderStatus::DRAFT);
    $order->confirm(); // DRAFT -> CONFIRMED, reserves 110 (available 10000 -> 9890)
    $invoice = BuyerInvoice::issueFromOrder($order->refresh());

    $this->buyer->refresh();
    expect((float) $this->buyer->available_credit)->toBe(9890.00);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionVisible(TestAction::make('recordPayment')->table($order->refresh()))
        ->callAction(TestAction::make('recordPayment')->table($order->refresh()), [
            'amount' => '110.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            'payment_date' => now()->toDateString(),
        ])
        ->assertNotified('Payment recorded');

    $this->buyer->refresh();

    expect(BuyerPayment::query()->where('buyer_invoice_id', $invoice->getKey())->count())->toBe(1)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::PAID)
        ->and((float) $this->buyer->available_credit)->toBe(10000.00);
});

it('hides recordPayment when the order has no invoice', function (): void {
    $order = invoiceActionOrder($this, OrderStatus::CONFIRMED);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertActionHidden(TestAction::make('recordPayment')->table($order));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php --filter="records a payment"`
Expected: FAIL — action `recordPayment` does not exist.

- [ ] **Step 3: Add the `recordPayment` action**

In `BuyerOrdersRelationManager.php`, add these imports:

```php
use App\Enums\PaymentMethod;
use App\Models\BuyerPayment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
```

(Some may already be imported — verify and add only the missing ones.)

Add a private helper method to the class to fetch the order's active invoice:

```php
private function activeInvoiceFor(BuyerOrder $order): ?BuyerInvoice
{
    return BuyerInvoice::query()
        ->where('buyer_order_id', $order->getKey())
        ->where('type', \App\Enums\InvoiceType::STANDARD)
        ->whereNot('status', \App\Enums\InvoiceStatus::CANCELLED)
        ->latest('id')
        ->first();
}
```

Then add this action immediately after the `issueInvoice` action:

```php
Action::make('recordPayment')
    ->label('Record Payment')
    ->icon('heroicon-o-banknotes')
    ->color('success')
    ->visible(function (?BuyerOrder $record): bool {
        if ($record === null) {
            return false;
        }
        $invoice = $this->activeInvoiceFor($record);

        return $invoice !== null && $invoice->status->canRecordPayment();
    })
    ->form(fn (BuyerOrder $record): array => [
        TextInput::make('amount')
            ->label('Amount')
            ->numeric()
            ->required()
            ->default(fn (): string => (string) ($this->activeInvoiceFor($record)?->amount_outstanding ?? 0)),
        Select::make('payment_method')
            ->label('Payment Method')
            ->options(PaymentMethod::class)
            ->default(PaymentMethod::BANK_TRANSFER->value)
            ->required(),
        DatePicker::make('payment_date')
            ->label('Payment Date')
            ->default(now())
            ->required(),
        TextInput::make('reference_number')
            ->label('Reference')
            ->maxLength(255),
    ])
    ->action(function (BuyerOrder $record, array $data): void {
        $invoice = $this->activeInvoiceFor($record);

        if ($invoice === null || ! $invoice->status->canRecordPayment()) {
            Notification::make()
                ->title('Cannot record payment')
                ->body('There is no open invoice to record a payment against.')
                ->danger()
                ->send();

            return;
        }

        BuyerPayment::create([
            'team_id' => $invoice->team_id,
            'buyer_invoice_id' => $invoice->getKey(),
            'payment_method' => $data['payment_method'],
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'reference_number' => $data['reference_number'] ?? null,
        ]);

        Notification::make()
            ->title('Payment recorded')
            ->body('The payment has been recorded and the invoice updated.')
            ->success()
            ->send();
    }),
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`
Expected: PASS (5 passed).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php
git add app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php
git commit -m "Add Record Payment action to the Invoices tab"
```

---

### Task 7: Surface invoice status & termin in the Invoices tab

Show the issued invoice's status, due date, and outstanding amount as table columns so staff can see payment state at a glance.

**Files:**
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php`
- Test: append one assertion to `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`

**Interfaces:**
- Consumes: the `buyerInvoices` relation on `BuyerOrder` (added in this task), `BuyerInvoice->status`, `->due_at`, `->amount_outstanding`.
- Produces: an `invoice_status` and `invoice_due` display in the table.

- [ ] **Step 1: Add the relation to `BuyerOrder`**

In `app/Models/BuyerOrder.php`, add:

```php
/**
 * Invoices issued from this order.
 *
 * @return HasMany<BuyerInvoice, $this>
 */
public function buyerInvoices(): HasMany
{
    return $this->hasMany(BuyerInvoice::class);
}
```

Add `* @property-read \Illuminate\Database\Eloquent\Collection<int, BuyerInvoice> $buyerInvoices` to the docblock.

- [ ] **Step 2: Write the failing test**

Append to `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`:

```php
it('shows the invoice status once issued', function (): void {
    $order = invoiceActionOrder($this, OrderStatus::CONFIRMED);
    BuyerInvoice::issueFromOrder($order);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->assertSee('Sent');
});
```

- [ ] **Step 3: Run the test to verify it fails or passes**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php --filter="shows the invoice status"`
Expected: FAIL — the invoice status is not rendered in the relation-manager table yet. (If "Sent" happens to already appear from another element, change the assertion to `->assertSee('Not issued')` on a second, un-issued order in the same request so the new column is genuinely exercised.)

- [ ] **Step 4: Add invoice columns to the table**

In the `->columns([ ... ])` array of `table()` in `BuyerOrdersRelationManager.php`, add after the existing status column:

```php
TextColumn::make('buyerInvoices.status')
    ->label('Invoice')
    ->badge()
    ->placeholder('Not issued'),
TextColumn::make('buyerInvoices.due_at')
    ->label('Due')
    ->date()
    ->placeholder('—'),
```

Ensure the relation is eager-loaded: find the `->modifyQueryUsing(...)` call (if present) and add `->with('buyerInvoices')`, or add `->recordActions()` unaffected. If no `modifyQueryUsing` exists, add:

```php
->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with('buyerInvoices'))
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`
Expected: PASS (all tests in the file).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php app/Models/BuyerOrder.php tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php
git add app/Models/BuyerOrder.php app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php
git commit -m "Surface issued invoice status and due date on the Invoices tab"
```

---

### Task 8: Full regression pass

**Files:** none (verification only).

- [ ] **Step 1: Run the buyer-order / invoice / credit test suites**

Run:
```bash
php artisan test --compact \
  tests/Feature/Erp/BuyerOrderTest.php \
  tests/Feature/Erp/BuyerInvoiceTest.php \
  tests/Feature/Erp/BuyerOrderCreditReleaseTest.php \
  tests/Feature/Erp/BuyerPaymentCreditReleaseTest.php \
  tests/Feature/Erp/BuyerInvoiceIssueFromOrderTest.php \
  tests/Feature/Filament/App/Resources/BuyerOrderActionTest.php \
  tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php \
  tests/Unit/Models/CompanyCreditLimitTest.php \
  tests/Feature/Erp/DashboardWidgetTest.php
```
Expected: all PASS.

- [ ] **Step 2: Static analysis and formatting**

Run: `vendor/bin/pint --test && composer test:types`
Expected: no style violations; PHPStan passes (fix any new findings).

- [ ] **Step 3: Ask the user before running the full suite**

Report results and ask whether to run `php artisan test --compact` in full.

---

## Notes / v1 limitations (carried from the spec)

- **One active invoice per order.** `issueFromOrder()` refuses a second non-cancelled standard invoice.
- **Credit release gate.** Credit is only released for orders that actually reserved it (`hasReservedCredit()` — a `debit` history row exists). Orders confirmed with `credit_status` off never reserved, so payments against them do not move credit.
- **Existing `restoreCredit()` inflation bug is out of scope.** `restoreCredit()` already mutates `available_credit` even when `credit_status` is off; this plan preserves that behaviour (now bounded by `credit_released`) and does not fix the pre-existing issue.
- **No new email template type.** The invoice email uses `InvoiceToBuyerMail` via `sendWithTeamSettings()` with no template config (default mailer, no CC/BCC). A `TYPE_BUYER_INVOICE` template is a future enhancement.
- **Multi-milestone termin** (separate due dates per installment) remains out of scope; partial payments against a single `due_at` cover the installment case.
- **Row locking is not truly concurrent.** `reconcileReleasedCreditFor()` copies the existing `confirm()`/`restoreCredit()` pattern (`$buyer->lockForUpdate(); $buyer->refresh();`), but `lockForUpdate()` on a loaded model instance is a no-op — it does not lock the row. This matches existing code and is safe for single-request flows; a genuine `SELECT ... FOR UPDATE` (re-query `Company::whereKey(...)->lockForUpdate()->first()`) across `confirm`/`restoreCredit`/`reconcile` is a separate hardening task, not covered here. Do **not** claim concurrency safety until that lands.
```

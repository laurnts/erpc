# Supplier Quote Tender Sequencing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Supplier Quotes tab's header buttons reflect the real tender sequence, and close the path that lets a user announce supplier outcomes without an approved Quotation Evaluation.

**Architecture:** Three button-gate corrections in one Filament relation manager, plus deletion of a duplicate announce path in a Livewire component and its Blade view. No changes to the backend stage gate, the announce action, or the QE approval flow — those are already correct; the UI simply contradicts them.

**Tech Stack:** Laravel 12, Filament v5, Livewire v4, Pest 4, PostgreSQL. All tooling runs through the Docker wrapper: `php vendor/bin/<tool>`, never bare `rector`/`pint`/`phpstan`.

**Spec:** `docs/superpowers/specs/2026-07-29-supplier-quote-tender-sequencing-design.md`

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`.
- All classes are `final` by default.
- Always `===` / `!==`, never `==` / `!=`.
- All methods carry explicit return types.
- Governing business rule for this change: **announcing outcomes requires an approved QE. No exceptions.**
- Compare Quotes locks at `QEStatus::APPROVED` — *not* at first winner selection. `applySelections()` is deliberately re-runnable and `QEStatus` has no rejected state, so the winner must stay correctable while a QE sits in `NEED_APPROVAL`.
- Before finalizing each task, run the scoped quality gates on the files you changed:
  ```bash
  php vendor/bin/rector process <changed files>
  php vendor/bin/pint --dirty
  ```
- **One-time setup before starting:** `.githooks/pre-commit` is currently not executable, so the scoped Rector/Pint gate does not run on commit in this clone. Fix it once:
  ```bash
  chmod +x .githooks/pre-commit
  git config core.hooksPath .githooks
  ```

## File Structure

| File | Change | Responsibility |
|---|---|---|
| `app/Filament/Resources/RequestResource/RelationManagers/SupplierQuotesRelationManager.php` | Modify (lines 1149–1244) | Header action gates for Compare Quotes and Create QE |
| `app/Livewire/SupplierQuoteComparison.php` | Modify | Remove the duplicate announce path and its now-dead helper |
| `resources/views/livewire/supplier-quote-comparison.blade.php` | Modify (lines 64–73) | Remove the Announce Outcomes button |
| `tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php` | Create | Cover every Compare Quotes / Create QE gate state |
| `tests/Feature/SupplierPortal/SupplierRequestOutcomeTest.php` | Modify (line ~276) | Migrate one test off the deleted Livewire method |

---

### Task 1: Compare Quotes — always visible, disabled with a reason

The tender entrance currently *hides* itself below two quotes (`SupplierQuotesRelationManager.php:1161`). Replace that visibility gate with a disabled gate carrying a tooltip, so the step stays in the flow and explains what unblocks it.

**Files:**
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/SupplierQuotesRelationManager.php:1150-1168`
- Test: `tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php` (create)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces:
  - `private function compareQuotesDisabledReason(): ?string` on `SupplierQuotesRelationManager` — returns the tooltip string when the action is unavailable, `null` when it can be opened. Not used by Tasks 2 or 3.
  - Test helpers used by Tasks 2 and 3: `supplierQuotesRelationManager(Tests\TestCase $test): Testable`, `headerActionQuote(Tests\TestCase $test, ?float $price = null, bool $obtained = false): SupplierQuote`, and `headerAction(string $name): TestAction`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\QEStatus;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Models\Company;
use App\Models\Currency;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->personalTeam();
    $this->actingAs($this->user);
    Filament::setTenant($this->team);

    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $this->request = Request::factory()
        ->for($this->team)
        ->recycle($this->buyer)
        ->create(['creator_id' => $this->user->getKey()]);
    $this->item = RequestItem::factory()->recycle($this->request)->withQuantity(1)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
});

/**
 * Mount the supplier quotes relation manager for the acting team's request.
 */
function supplierQuotesRelationManager(Tests\TestCase $test): Testable
{
    return livewire(SupplierQuotesRelationManager::class, [
        'ownerRecord' => $test->request,
        'pageClass' => ViewRequest::class,
    ]);
}

/**
 * Build a supplier quote for the acting request. Without a price the quote
 * stays PENDING; the item observer transitions it to RECEIVED once priced.
 */
function headerActionQuote(Tests\TestCase $test, ?float $price = null, bool $obtained = false): SupplierQuote
{
    $quote = SupplierQuote::factory()
        ->recycle($test->team)
        ->forRequest($test->request)
        ->forSupplier($test->supplier)
        ->withCurrency($test->currency)
        ->sentToSupplier()
        ->create(['obtained' => $obtained]);

    if ($price !== null) {
        SupplierQuoteItem::factory()
            ->forSupplierQuote($quote)
            ->forRequestItem($test->item)
            ->withPricing(1, $price)
            ->create();
    }

    return $quote->refresh();
}

/**
 * Relation manager header actions live on the *table*, not on the Livewire
 * component, so a bare string name resolves to null and the assertion fails
 * with "not an instance of Action". TestAction::make(...)->table() with no
 * record is the form that resolves a table header action.
 */
function headerAction(string $name): TestAction
{
    return TestAction::make($name)->table();
}

describe('compare quotes action', function (): void {
    it('renders disabled with a prompt when no supplier quote has a price', function (): void {
        headerActionQuote($this);

        supplierQuotesRelationManager($this)
            ->assertOk()
            ->assertActionVisible(headerAction('compareQuotes'))
            ->assertActionDisabled(headerAction('compareQuotes'));
    });

    it('enables with a single priced quote so a one-supplier tender is reachable', function (): void {
        headerActionQuote($this, 100.0);

        supplierQuotesRelationManager($this)
            ->assertOk()
            ->assertActionVisible(headerAction('compareQuotes'))
            ->assertActionEnabled(headerAction('compareQuotes'));
    });

    it('locks once the latest QE is approved', function (): void {
        headerActionQuote($this, 100.0);

        $approvedAt = now()->subHour();
        QuotationEvaluation::factory()
            ->forRequest($this->request)
            ->create([
                'creator_id' => $this->user->getKey(),
                'status' => QEStatus::APPROVED,
                'dept_head_sales_approved_at' => $approvedAt,
                'deputy_director_approved_at' => $approvedAt,
                'director_approved_at' => $approvedAt,
            ]);

        supplierQuotesRelationManager($this)
            ->assertOk()
            ->assertActionVisible(headerAction('compareQuotes'))
            ->assertActionDisabled(headerAction('compareQuotes'));
    });

    it('stays enabled while a QE is still awaiting approval so the winner can be corrected', function (): void {
        headerActionQuote($this, 100.0);

        QuotationEvaluation::factory()
            ->forRequest($this->request)
            ->create([
                'creator_id' => $this->user->getKey(),
                'status' => QEStatus::NEED_APPROVAL,
            ]);

        supplierQuotesRelationManager($this)
            ->assertOk()
            ->assertActionEnabled(headerAction('compareQuotes'));
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact --filter="compare quotes action"
```

Expected: FAIL. The first two cases fail because Compare Quotes is currently *hidden* — one quote is below today's `>= 2` threshold — so `assertActionVisible` fails. The approved-QE case fails because no QE gate exists yet.

This exact failure was confirmed by probing the current code before this plan was written, so a different failure means something else is wrong — investigate before implementing.

- [ ] **Step 3: Replace the visibility gate with a disabled gate**

In `SupplierQuotesRelationManager.php`, replace the `compareQuotes` action (lines 1150–1168) with:

```php
                Action::make('compareQuotes')
                    ->label('Compare Quotes')
                    ->icon('heroicon-o-scale')
                    ->color('info')
                    ->modalHeading('Compare Supplier Quotes')
                    ->modalWidth('7xl')
                    ->modalContent(fn (): View => view('filament.modals.supplier-quote-comparison', [
                        'request' => $this->getOwnerRecord(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->disabled(fn (): bool => $this->compareQuotesDisabledReason() !== null)
                    ->tooltip(fn (): ?string => $this->compareQuotesDisabledReason()),
```

- [ ] **Step 4: Add the reason helper**

Add this private method to `SupplierQuotesRelationManager`, directly after the `table()` method that contains the header actions:

```php
    /**
     * Why Compare Quotes cannot be opened, or null when it can.
     *
     * The tender entrance always renders — it is disabled rather than hidden so
     * the step stays visible in the flow and states what unblocks it. Locking
     * happens at QE approval, not at first selection: applySelections() is
     * re-runnable by design, so the winner stays correctable while the QE is
     * still awaiting approval.
     */
    private function compareQuotesDisabledReason(): ?string
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        $latestQE = $request->quotationEvaluations()->latest()->first();

        if ($latestQE !== null && $latestQE->status === QEStatus::APPROVED) {
            return 'QE approved — winner is final';
        }

        $hasPricedQuote = $request->supplierQuotes()
            ->whereIn('status', [SupplierQuoteStatus::RECEIVED, SupplierQuoteStatus::SELECTED])
            ->whereHas('items', function (Builder $query): void {
                $query->where('unit_price', '>', 0);
            })
            ->exists();

        return $hasPricedQuote ? null : 'Enter supplier prices first';
    }
```

The `RECEIVED`/`SELECTED` filter matches the modal's own data source (`SupplierQuoteComparison::quotes()`), so the button never enables onto an empty price matrix.

- [ ] **Step 5: Add the QEStatus import**

In the `use` block of `SupplierQuotesRelationManager.php`, add alongside the other enum imports (after `use App\Enums\PrepaymentType;`):

```php
use App\Enums\QEStatus;
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --compact --filter="compare quotes action"
```

Expected: PASS, 4 tests.

- [ ] **Step 7: Run the quality gates**

```bash
php vendor/bin/rector process app/Filament/Resources/RequestResource/RelationManagers/SupplierQuotesRelationManager.php tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php
php vendor/bin/pint --dirty
```

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/RequestResource/RelationManagers/SupplierQuotesRelationManager.php tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php
git commit -m "fix: keep Compare Quotes visible and disable it with a reason

The tender entrance hid itself below two quotes, so a single-supplier
request never saw it — even though selectSingleSupplier() supports a
one-supplier tender. It now always renders, disabled until a quote is
priced and again once the QE is approved."
```

---

### Task 2: Create QE — hidden until the tender produces a winner

Create QE currently renders greyed-out from the moment any quote is priced, advertising step 3 before a winner exists. Hide it until a `SELECTED` quote exists and drop the disabled state.

**Files:**
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/SupplierQuotesRelationManager.php:1189-1243`
- Test: `tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php` (append)

**Interfaces:**
- Consumes: the test helpers `supplierQuotesRelationManager()`, `headerActionQuote()`, and `headerAction()` created in Task 1.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

Append this `describe` block to `tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php`:

```php
describe('create QE action', function (): void {
    it('stays hidden while quotes are priced but no winner is selected', function (): void {
        headerActionQuote($this, 100.0);

        supplierQuotesRelationManager($this)
            ->assertOk()
            ->assertActionHidden(headerAction('createQE'));
    });

    it('appears once a supplier quote is selected as the winner', function (): void {
        $quote = headerActionQuote($this, 100.0);
        $quote->update(['status' => SupplierQuoteStatus::SELECTED]);

        supplierQuotesRelationManager($this)
            ->assertOk()
            ->assertActionVisible(headerAction('createQE'))
            ->assertActionEnabled(headerAction('createQE'));
    });

    it('stays hidden when a QE already exists for the request', function (): void {
        $quote = headerActionQuote($this, 100.0);
        $quote->update(['status' => SupplierQuoteStatus::SELECTED]);

        QuotationEvaluation::factory()
            ->forRequest($this->request)
            ->create(['creator_id' => $this->user->getKey()]);

        supplierQuotesRelationManager($this)
            ->assertOk()
            ->assertActionHidden(headerAction('createQE'));
    });

    it('stays hidden on the obtained path, which skips QE by design', function (): void {
        $quote = headerActionQuote($this, 100.0, obtained: true);
        $quote->update(['status' => SupplierQuoteStatus::SELECTED]);

        expect($this->request->refresh()->hasObtainedSelectedSupplierQuote())->toBeTrue();

        supplierQuotesRelationManager($this)
            ->assertOk()
            ->assertActionHidden(headerAction('createQE'));
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact --filter="create QE action"
```

Expected: FAIL on the first case — Create QE is currently visible (disabled) whenever a priced quote exists, so `assertActionHidden` fails.

- [ ] **Step 3: Rewrite the visibility gate and delete the disabled state**

In `SupplierQuotesRelationManager.php`, replace the whole `createQE` action (lines 1189–1243, ending just before the closing `])` of `headerActions`) with:

```php
                Action::make('createQE')
                    ->label('Create QE')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->modalHeading('Create Quotation Evaluation')
                    ->modalDescription('Generate an internal QE document from this quote comparison')
                    ->modalWidth('xl')
                    ->modalContent(fn (): View => view('filament.modals.create-quotation-evaluation', [
                        'request' => $this->getOwnerRecord(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->visible(function (): bool {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        // Only one QE per request.
                        if ($request->quotationEvaluations()->exists()) {
                            return false;
                        }

                        // An obtained+selected quote skips QE and goes straight
                        // to Buyer Quotes (see the 'obtained' checkbox helper text).
                        if ($request->hasObtainedSelectedSupplierQuote()) {
                            return false;
                        }

                        // Hidden until the tender has produced a winner. Visibility
                        // now means availability — there is no disabled state.
                        return $request->supplierQuotes()
                            ->where('status', SupplierQuoteStatus::SELECTED)
                            ->exists();
                    }),
```

The old "quotes with prices entered" check is dropped as redundant: a quote only reaches `SELECTED` after prices are entered, so the `SELECTED` check subsumes it. The `->disabled()` and `->tooltip()` blocks are deleted entirely.

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --compact --filter="create QE action"
```

Expected: PASS, 4 tests.

- [ ] **Step 5: Run the whole file to confirm Task 1 still passes**

```bash
php artisan test --compact tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php
```

Expected: PASS, 8 tests.

- [ ] **Step 6: Run the quality gates**

```bash
php vendor/bin/rector process app/Filament/Resources/RequestResource/RelationManagers/SupplierQuotesRelationManager.php tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php
php vendor/bin/pint --dirty
```

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/RequestResource/RelationManagers/SupplierQuotesRelationManager.php tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php
git commit -m "fix: hide Create QE until the tender has a winner

Create QE rendered greyed-out from the moment any quote was priced,
advertising step 3 before a winner existed. It is now hidden until a
SELECTED quote exists, so visibility means availability."
```

---

### Task 3: Delete the announce path that bypasses QE approval

`SupplierQuoteComparison::announceOutcomes()` and its Blade button let a user announce outcomes to suppliers with no QE at all — irreversibly, since `AnnounceSupplierRequestOutcomes` marks losers `REJECTED`, emails suppliers, and stamps `outcomes_announced_at`. The QE page's equivalent (`ViewQuotationEvaluation:97`) correctly requires `QEStatus::APPROVED` and becomes the only route.

Deleting the method rather than guarding it removes the `wire:call` surface entirely — a template conditional alone would not stop a direct Livewire call.

**Files:**
- Modify: `app/Livewire/SupplierQuoteComparison.php`
- Modify: `resources/views/livewire/supplier-quote-comparison.blade.php:64-73`
- Modify: `tests/Feature/SupplierPortal/SupplierRequestOutcomeTest.php` (the test at ~line 276)
- Test: `tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php` (append)

**Interfaces:**
- Consumes: the test helpers from Task 1.
- Produces: nothing. After this task `SupplierQuoteComparison` has no `announceOutcomes()` method and no `hasAppliedSelections()` computed property.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php`:

```php
describe('announce outcomes is reachable only after QE approval', function (): void {
    it('no longer exposes announceOutcomes on the comparison component', function (): void {
        expect(method_exists(App\Livewire\SupplierQuoteComparison::class, 'announceOutcomes'))
            ->toBeFalse('Announcing must go through the QE page, which gates on QEStatus::APPROVED.');
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact --filter="no longer exposes announceOutcomes"
```

Expected: FAIL — the method still exists.

- [ ] **Step 3: Remove the Announce Outcomes button from the Blade view**

In `resources/views/livewire/supplier-quote-comparison.blade.php`, delete this entire block (lines 64–73):

```blade
                @if($this->hasAppliedSelections && ! $this->outcomesAnnounced)
                    <x-filament::button
                        size="sm"
                        color="warning"
                        wire:click="announceOutcomes"
                        wire:confirm="Announce outcomes to suppliers? Losing quotes will be marked as rejected, suppliers will be notified of their result, and selections will be locked for this request. This cannot be undone."
                        icon="heroicon-o-megaphone"
                    >
                        Announce Outcomes
                    </x-filament::button>
                @endif
```

Leave everything else untouched — the "selections locked" badge (line 27) and the `! $this->outcomesAnnounced` conditions on Select Best Prices / Clear / Apply (lines 33, 44) all stay.

- [ ] **Step 4: Delete the method and its now-dead helper**

In `app/Livewire/SupplierQuoteComparison.php`, delete:

1. The entire `announceOutcomes()` method (lines 236–266, including its docblock beginning "Announce won/lost outcomes to suppliers for this request's round.").
2. The entire `hasAppliedSelections()` computed method (lines 276–290, including its docblock). It was read only by the Blade button just deleted, so it is now dead.
3. The `$this->hasAppliedSelections` entry from the `unset(...)` call inside `applySelections()` (line 224). It becomes:

```php
        unset($this->quotes, $this->priceMatrix, $this->bestPricesByItem);
```

4. The `@property-read bool $hasAppliedSelections` line from the class docblock (line 37).
5. The now-unused import (line 7):

```php
use App\Actions\SupplierPortal\AnnounceSupplierRequestOutcomes;
```

Keep `Gate`, `Notification`, `Builder`, and `SupplierQuoteItem` — `applySelections()` still uses all four.

- [ ] **Step 5: Migrate the existing test off the deleted method**

In `tests/Feature/SupplierPortal/SupplierRequestOutcomeTest.php`, the test `it('locks applySelections for the round after announcement', ...)` calls the deleted method. Replace its opening sequence:

```php
        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections')
            ->call('announceOutcomes');
```

with a direct call to the action, matching the sibling tests in that file (lines 152, 203, 229, 266):

```php
        livewire(SupplierQuoteComparison::class, ['request' => $this->request])
            ->call('selectSingleSupplier', $this->quoteA->getKey())
            ->call('applySelections');

        app(AnnounceSupplierRequestOutcomes::class)->execute($this->request);
```

The rest of the test — asserting the round locks and a later `applySelections()` is refused — is unchanged and still covers the behavior that matters. `AnnounceSupplierRequestOutcomes` is already imported at the top of that file (line 5).

- [ ] **Step 6: Run both test files to verify they pass**

```bash
php artisan test --compact tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php tests/Feature/SupplierPortal/SupplierRequestOutcomeTest.php
```

Expected: PASS, 9 tests in the first file and the full outcome suite in the second.

- [ ] **Step 7: Confirm the QE page's announce action is untouched**

```bash
php artisan test --compact tests/Feature/Filament/App/Resources/CreditAcceptanceAndQeApproveActionTest.php
```

Expected: PASS. This file exercises QE approval; it must not regress.

- [ ] **Step 8: Run the quality gates**

```bash
php vendor/bin/rector process app/Livewire/SupplierQuoteComparison.php tests/Feature/SupplierPortal/SupplierRequestOutcomeTest.php tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php
php vendor/bin/pint --dirty
```

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/SupplierQuoteComparison.php resources/views/livewire/supplier-quote-comparison.blade.php tests/Feature/SupplierPortal/SupplierRequestOutcomeTest.php tests/Feature/Filament/App/Resources/SupplierQuoteHeaderActionsTest.php
git commit -m "fix: require an approved QE before announcing supplier outcomes

The comparison modal had its own Announce Outcomes button gated only on
applied selections, so a user could notify suppliers and irreversibly lock
the round with no QE at all. The QE page's action already requires
QEStatus::APPROVED and is now the only route.

Deletes the Livewire method rather than guarding it, so no wire:call
surface remains for an irreversible, supplier-facing action."
```

---

### Task 4: Full-suite verification and manual check

**Files:** none modified.

**Interfaces:**
- Consumes: all three preceding tasks.
- Produces: nothing.

- [ ] **Step 1: Run the ERP and Filament suites**

```bash
php artisan test --compact tests/Feature/Erp tests/Feature/Filament tests/Feature/SupplierPortal
```

Expected: PASS. Investigate any failure before proceeding — do not proceed on red.

- [ ] **Step 2: Run static analysis and architecture tests**

```bash
composer test:types
composer test:arch
```

Expected: PASS both. `test:types` catches a missed import or a wrong return type on the new `compareQuotesDisabledReason()` helper.

- [ ] **Step 3: Manually verify against the reported request**

Visit `http://app.erpc.test/1/requests/15?relation=1` (REQ-2026-4273 — two `received` quotes, none selected) and confirm:

| Expectation | Why |
|---|---|
| Compare Quotes is visible and **enabled** | Both quotes are priced |
| Create QE is **absent** | No `SELECTED` quote — this is the reported bug |
| Opening Compare Quotes shows **no** Announce Outcomes button | Task 3 |

Then, inside the modal, select a supplier and click Apply. Close the modal and confirm **Create QE now appears**.

- [ ] **Step 4: Report results**

State plainly which commands were run and what they output. If any step failed or was skipped, say so explicitly rather than reporting the task complete.

## Self-Review Notes

**Spec coverage:**

| Spec section | Task |
|---|---|
| Design 1 — Compare Quotes disabled gate, price condition, QE-approved lock | Task 1 |
| Design 2 — Create QE hidden until `SELECTED`, disabled block deleted | Task 2 |
| Design 3 — delete Blade button, delete `announceOutcomes()`, keep `outcomesAnnounced`, leave QE page unchanged | Task 3 |
| Accepted consequence — obtained path forfeits announcements | Task 2 test (`stays hidden on the obtained path`) documents the skip; Task 3 removes the only non-QE announce route |
| Testing section — all seven header-action cases plus the announce removal | Tasks 1–3 |
| Out of scope — stage gate, `obtained` checkbox, `AnnounceSupplierRequestOutcomes`, `QEStatus` rework state | Untouched; Task 3 Step 7 guards against QE-approval regression |

**Type consistency:** `compareQuotesDisabledReason(): ?string` is defined in Task 1 and referenced only there. `hasObtainedSelectedSupplierQuote()` and `quotationEvaluations()` are pre-existing on `Request`. The test helpers `supplierQuotesRelationManager()`, `headerActionQuote()`, and `headerAction()` are defined once in Task 1 and reused with matching signatures in Tasks 2 and 3.

**Verified against the running codebase while writing this plan** (not assumed):

| Assumption | How it was checked | Result |
|---|---|---|
| `TestAction::make($name)->table()` is required for relation manager header actions | Temporary probe test run against the real relation manager | Confirmed — the **bare string form does not work**: it resolves to `null` and fails with "not an instance of `Filament\Actions\Action`". The bare-string calls in `ProfitAndLossApproveActionTest` are *page* actions, a different resolution scope |
| Compare Quotes is hidden with a single priced quote today | Same probe | Confirmed — matches the `>= 2` gate at line 1161 |
| `obtained` is mass-assignable | `SupplierQuote::$fillable` | Confirmed (line 115) |
| `assertActionVisible` / `Hidden` / `Enabled` / `Disabled` all exist in Filament v5 | `vendor/filament/actions/src/Testing/TestsActions.php` | Confirmed (lines 225, 239, 253, 266) |
| A quote with no priced item stays `PENDING`; the item observer promotes it to `RECEIVED` | `SupplierQuoteFactory::definition()` + `SupplierRequestOutcomeTest` helper docblock | Confirmed |
| `QuotationEvaluationFactory` has **no** `approved()` state | Read the factory | Confirmed — approval fields must be set explicitly, as the plan's tests do |

**Deviation to expect:** Task 3 Step 4 cites line numbers from the file as it stands today. Tasks 1 and 2 do not modify `SupplierQuoteComparison.php`, so those numbers remain valid — but locate the code by its method name and docblock rather than trusting the line number.

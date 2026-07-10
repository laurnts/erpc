# Post-Review Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix every remaining finding from the 2026-07-09 code review that was not covered by commit `a43d7aa6` — email-send hardening/dedup, badge and stage-logic single-sourcing, render-path efficiency, and static-analysis debt in today's files.

**Architecture:** Small, independent refactors on the Filament/portal layer. Shared logic moves DOWN (enum methods, a shared Portal resolver service, email partials); view/handler layers become thin consumers. No schema or dependency changes.

**Tech Stack:** Laravel 12, Filament v5, Livewire v4, Pest 4, PHPStan (larastan), Pint.

## Global Constraints

- Every PHP file: `declare(strict_types=1);` (`.claude/rules/core.md`)
- All new classes `final`; services `final readonly` (`.claude/rules/core.md`)
- Comparisons: `===` / `!==` only (`.claude/rules/core.md`)
- Explicit return types on all methods (`.claude/rules/core.md`)
- Run `vendor/bin/pint --dirty` before finalizing each task (CLAUDE.md pint rules)
- Tests: Pest, run with `php artisan test --compact <file>` (CLAUDE.md pest rules)
- TDD: every behavior change gets a failing test first
- Commit after each task; stage files explicitly by path (never `git add -A`)

---

### Task 1: Harden and simplify `sendInvoiceEmailToBuyer`

Whitespace-only buyer emails currently pass the `empty()` guard and reach `Mail::to(' ')`; four pre-interpolated message strings are threaded through a 6-parameter signature and can embed a blank address.

**Files:**
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php:267-310` (helper), `:640-672` (issueInvoice caller), `:683-693` (resendInvoice modalDescription), `:720-730` (resendInvoice caller)
- Test: `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`

**Interfaces:**
- Produces: `private function sendInvoiceEmailToBuyer(BuyerOrder $record, BuyerInvoice $invoice, bool $isResend): void` — resolves and trims the buyer email itself and builds all notification copy internally.

- [ ] **Step 1: Write the failing test** (append to `BuyerInvoiceActionTest.php`; it reuses the file's existing `beforeEach` and the `invoiceActionOrder`/`invoiceActionRelationManager` helpers)

```php
it('warns instead of sending when the buyer email is whitespace only', function (): void {
    Mail::fake();

    $this->buyer->update(['email' => '   ']);
    $order = invoiceActionOrder($this, OrderStatus::CONFIRMED);
    BuyerInvoice::issueFromOrder($order);

    invoiceActionRelationManager($this)
        ->assertOk()
        ->callAction(TestAction::make('resendInvoice')->table($order->refresh()))
        ->assertNotified('Cannot send email');

    Mail::assertNothingSent();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php --filter="whitespace only"`
Expected: FAIL — either an exception from the mailer or a missing `Cannot send email` notification (the `' '` address passes `empty()`).

- [ ] **Step 3: Replace the helper** (delete the old 6-parameter version at lines 267-310 entirely)

```php
private function sendInvoiceEmailToBuyer(BuyerOrder $record, BuyerInvoice $invoice, bool $isResend): void
{
    $buyerEmail = trim((string) ($record->buyer->email ?? ''));
    $buyerName = $record->buyer->name ?? 'Buyer';

    if ($buyerEmail === '') {
        Notification::make()
            ->title('Cannot send email')
            ->body("The buyer ({$buyerName}) does not have an email address configured.")
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
            ->title($isResend ? 'Email resent' : 'Invoice issued')
            ->body($isResend
                ? "Invoice email has been resent successfully to {$buyerEmail}."
                : "Invoice has been issued and sent to {$buyerEmail}.")
            ->success()
            ->send();
    } catch (\Exception $e) {
        Log::error('Failed to send buyer invoice email', [
            'invoice_id' => $invoice->id,
            'buyer_order_id' => $record->id,
            'buyer_email' => $buyerEmail,
            'error' => $e->getMessage(),
        ]);

        Notification::make()
            ->title($isResend ? 'Failed to resend email' : 'Invoice issued (email failed)')
            ->body(($isResend
                ? "The invoice email could not be sent to {$buyerEmail}. Error: "
                : "Invoice was created, but the email to {$buyerEmail} could not be sent. Error: ")
                .$e->getMessage())
            ->danger()
            ->send();
    }
}
```

- [ ] **Step 4: Update the issueInvoice caller** — replace the block from `$buyerEmail = $record->buyer->email ?? null;` through the closing `);` of the old 6-arg call with:

```php
$buyerEmail = trim((string) ($record->buyer->email ?? ''));

if ($buyerEmail === '') {
    Notification::make()
        ->title('Invoice issued')
        ->body('Invoice has been created, but no email was sent because the buyer has no email address.')
        ->warning()
        ->send();

    return;
}

$this->sendInvoiceEmailToBuyer($record, $invoice, isResend: false);
```

- [ ] **Step 5: Update the resendInvoice caller** — delete its `$buyerEmail = $record->buyer->email ?? null;` line and replace the old 6-arg call with:

```php
$this->sendInvoiceEmailToBuyer($record, $invoice, isResend: true);
```

- [ ] **Step 6: Trim in resendInvoice's `modalDescription`** — change `$buyerEmail = $record->buyer->email ?? null;` to `$buyerEmail = trim((string) ($record->buyer->email ?? ''));` and `if (empty($buyerEmail))` to `if ($buyerEmail === '')`.

- [ ] **Step 7: Run the new test and the whole file**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`
Expected: ALL PASS (including the existing issue/resend/notification-copy tests — the helper reproduces the exact same strings).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php
git commit -m "refactor: harden buyer invoice email send, build copy inside helper"
```

---

### Task 2: Single-source the note-visibility badge on `NoteVisibility`

The badge color/label match is hand-rolled in two blades; `NoteVisibility` already has `getColor()`/`getIcon()` but its `getLabel()` copy ("Shared with buyer") differs from the timeline badges ("Notes: To Buyer").

**Files:**
- Modify: `app/Enums/NoteVisibility.php` (add method after `getColor()`)
- Modify: `resources/views/timeline/portal-timeline.blade.php:41-49`
- Modify: `resources/views/livewire/request-history-timeline.blade.php:33-41`
- Test: `tests/Unit/Enums/NoteVisibilityTest.php` (create if absent; `Unit/Enums` is wired as pure no-DB in `tests/Pest.php`)

**Interfaces:**
- Produces: `NoteVisibility::getTimelineBadgeLabel(): string`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Enums\NoteVisibility;

it('exposes timeline badge labels per visibility', function (): void {
    expect(NoteVisibility::Buyer->getTimelineBadgeLabel())->toBe('Notes: To Buyer')
        ->and(NoteVisibility::Supplier->getTimelineBadgeLabel())->toBe('Notes: To Supplier')
        ->and(NoteVisibility::Internal->getTimelineBadgeLabel())->toBe('Notes: Internal');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Enums/NoteVisibilityTest.php`
Expected: FAIL — `Call to undefined method ... getTimelineBadgeLabel()`

- [ ] **Step 3: Add the enum method** (after `getColor()`)

```php
/**
 * Badge label used by the staff and portal activity timelines.
 */
public function getTimelineBadgeLabel(): string
{
    return match ($this) {
        self::Internal => 'Notes: Internal',
        self::Buyer => 'Notes: To Buyer',
        self::Supplier => 'Notes: To Supplier',
    };
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Enums/NoteVisibilityTest.php`
Expected: PASS

- [ ] **Step 5: Swap both blades onto the enum.** In `resources/views/timeline/portal-timeline.blade.php` replace the badge block inside the `ENTRY_NOTE` conditional with:

```blade
@php $noteVisibility = \App\Enums\NoteVisibility::tryFrom((string) ($entry->properties['visibility'] ?? '')) ?? \App\Enums\NoteVisibility::Internal; @endphp
<x-filament::badge
    :color="$noteVisibility->getColor()"
    icon="heroicon-o-chat-bubble-left-right"
>
    {{ $noteVisibility->getTimelineBadgeLabel() }}
</x-filament::badge>
```

In `resources/views/livewire/request-history-timeline.blade.php` replace its equivalent block (the one defaulting to `'internal'`) with the identical snippet above.

- [ ] **Step 6: Run the blade-covering tests**

Run: `php artisan test --compact tests/Unit/Erp/PortalTimelineSourceTest.php tests/Feature/BuyerPortal/BuyerRequestActivityTimelineTest.php tests/Feature/Erp/RequestNoteTest.php`
Expected: ALL PASS. If a test asserted the old literal `'Note'` fallback text, update it to `'Notes: Internal'` — the portal never receives internal notes, so this fallback is unreachable in production.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Enums/NoteVisibility.php resources/views/timeline/portal-timeline.blade.php resources/views/livewire/request-history-timeline.blade.php tests/Unit/Enums/NoteVisibilityTest.php
git commit -m "refactor: single-source note-visibility badge on NoteVisibility enum"
```

---

### Task 3: Type the untyped `$state` closure parameter

**Files:**
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php` (the `unit_price` field's `afterStateUpdated`, currently `function (Set $set, Get $get, $state): void`)

- [ ] **Step 1: Add the type.** Change the closure signature to `function (Set $set, Get $get, mixed $state): void` (matches the sibling at the file's `cost_price` handler which uses `mixed $state`).

- [ ] **Step 2: Run the covering tests**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerQuoteMarginFormTest.php`
Expected: 4 PASS

- [ ] **Step 3: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Resources/RequestResource/RelationManagers/BuyerQuotesRelationManager.php
git commit -m "style: type unit_price afterStateUpdated state parameter"
```

---

### Task 4: Build the buyer activity feed once per render

`ViewBuyerRequest` builds the full multi-table timeline twice per render: `activityCount()` for the section heading and the `ViewEntry` state closure for the body.

**Files:**
- Modify: `app/Filament/Buyer/Resources/BuyerRequestResource/Pages/ViewBuyerRequest.php:211-222` (heading + state closure) and `:273-279` (`activityCount()`)
- Test: existing `tests/Feature/BuyerPortal/BuyerRequestActivityTimelineTest.php` (behavior-preserving refactor)

**Interfaces:**
- Produces: `private function buyerTimeline(Request $record): array` returning `list<\App\Data\TimelineEntry>`, memoized per Livewire request.

- [ ] **Step 1: Add the memoized accessor and delete `activityCount()`**

```php
/** @var list<\App\Data\TimelineEntry>|null */
private ?array $memoizedBuyerTimeline = null;

/**
 * Buyer-scoped activity feed, built once per Livewire render — the section
 * heading (count) and the timeline body both consume it.
 *
 * @return list<\App\Data\TimelineEntry>
 */
private function buyerTimeline(Request $record): array
{
    return $this->memoizedBuyerTimeline ??= app(PortalTimelineSource::class)->forParty(
        $record,
        TimelineParty::buyer(app(BuyerPortalContext::class)->companyId()),
    );
}
```

- [ ] **Step 2: Point both consumers at it.** Heading: `Section::make(fn (Request $record): string => 'Activities · '.count($this->buyerTimeline($record)))`. ViewEntry state: `->state(fn (Request $record): array => $this->buyerTimeline($record))`.

- [ ] **Step 3: Run the covering tests**

Run: `php artisan test --compact tests/Feature/BuyerPortal/BuyerRequestActivityTimelineTest.php tests/Feature/BuyerPortal/BuyerPortalTest.php`
Expected: ALL PASS

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Buyer/Resources/BuyerRequestResource/Pages/ViewBuyerRequest.php
git commit -m "perf: build buyer activity feed once per render"
```

---

### Task 5: Trim invoice-email eager loads

The template eager-loads `buyerOrder.buyerQuote`, which nothing in the template, partial, or `InvoiceToBuyerMail` reads; the partial then re-runs an overlapping `loadMissing`.

**Files:**
- Modify: `resources/views/emails/invoice-to-buyer.blade.php:2`
- Modify: `resources/views/emails/partials/buyer-invoice-items-table.blade.php:3`
- Test: existing render test in `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php` ("renders the default invoice email template")

- [ ] **Step 1: Consolidate.** In `invoice-to-buyer.blade.php` line 2:

```php
$invoice->loadMissing(['items.unitOfMeasure', 'currency', 'buyerOrder.buyer', 'request']);
```

In `buyer-invoice-items-table.blade.php` delete its `$invoice->loadMissing(['items.unitOfMeasure', 'currency']);` line (the parent template now guarantees both; the partial keeps reading `$invoice->items` / `$invoice->currency`).

- [ ] **Step 2: Run the covering tests**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`
Expected: ALL PASS

- [ ] **Step 3: Commit**

```bash
git add resources/views/emails/invoice-to-buyer.blade.php resources/views/emails/partials/buyer-invoice-items-table.blade.php
git commit -m "perf: drop unused buyerQuote eager load from invoice email"
```

---

### Task 6: Extract `RequestEffectiveStageResolver` shared by both portals

The effective-stage resolution (sent-quote promotion + post-confirmation resolution) lives in `BuyerRequestStagePresenter`; the supplier stepper still renders raw `$request->stage`, so a stale stage shows wrong on the supplier portal.

**Files:**
- Create: `app/Services/Portal/RequestEffectiveStageResolver.php`
- Modify: `app/Services/BuyerPortal/BuyerRequestStagePresenter.php` (delegate; delete moved privates)
- Modify: `app/Services/SupplierPortal/SupplierRequestStagePresenter.php`
- Test: `tests/Unit/BuyerPortal/BuyerRequestStagePresenterTest.php` (stays green — proves delegation), plus new supplier assertions appended there

**Interfaces:**
- Produces: `RequestEffectiveStageResolver::effectiveStage(Request $request): RequestStage` (`final readonly`, DB-backed fallbacks identical to the current private methods).
- Consumes: `RequestWorkflowTimelinePresenter::timeline(Request $request, RequestStage $activeStage): array`.

- [ ] **Step 1: Write the failing test** (append to `BuyerRequestStagePresenterTest.php`)

```php
it('applies the effective stage to the supplier timeline as well', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
    ]);

    BuyerQuote::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->accepted()
        ->create();

    $timeline = app(\App\Services\SupplierPortal\SupplierRequestStagePresenter::class)->timeline($request);
    $current = collect($timeline)->firstWhere('current', true);

    expect($current['stage'])->toBe(RequestStage::PREPARING_SUPPLIER_ORDER);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/BuyerPortal/BuyerRequestStagePresenterTest.php --filter="supplier timeline"`
Expected: FAIL — current stage is `AWAITING_BUYER_CONFIRMATION` (supplier presenter passes raw `$request->stage`).

- [ ] **Step 3: Create the resolver** — move `effectiveStage()`, `requestHasSentBuyerQuote()`, `buyerConfirmationIsComplete()`, and `resolvePostConfirmationStage()` VERBATIM out of `BuyerRequestStagePresenter` into:

```php
<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\BuyerQuoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RequestStage;
use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\BuyerQuote;
use App\Models\Request;

/**
 * The stage a portal user (buyer or supplier) should perceive as current,
 * independent of a possibly stale internal stage. Shared by both portal
 * stage presenters so the two steppers can never disagree.
 */
final readonly class RequestEffectiveStageResolver
{
    public function effectiveStage(Request $request): RequestStage
    {
        // ... body moved verbatim from BuyerRequestStagePresenter::effectiveStage()
    }

    // requestHasSentBuyerQuote(), buyerConfirmationIsComplete(),
    // resolvePostConfirmationStage() — moved verbatim as private methods.
}
```

(The method bodies are copied unchanged from `app/Services/BuyerPortal/BuyerRequestStagePresenter.php` — the class currently at HEAD after commit `a43d7aa6`.)

- [ ] **Step 4: Slim the buyer presenter.** `BuyerRequestStagePresenter` keeps `label()`, `labelForStage()`, `partyFacingLabel()`, `color()`, `timeline()`; its constructor becomes:

```php
public function __construct(
    private RequestWorkflowTimelinePresenter $timelinePresenter,
    private RequestEffectiveStageResolver $stageResolver,
) {}

public function effectiveStage(Request $request): RequestStage
{
    return $this->stageResolver->effectiveStage($request);
}
```

Delete the four moved private/public method bodies and now-unused imports (`BuyerQuoteStatus`, `InvoiceStatus`, `ShipmentStatus`, `ShipmentType`, `BuyerQuote`).

- [ ] **Step 5: Wire the supplier presenter.** Full new content of `SupplierRequestStagePresenter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\SupplierPortal;

use App\Models\Request;
use App\Services\Portal\RequestEffectiveStageResolver;
use App\Services\Portal\RequestWorkflowTimelinePresenter;

final readonly class SupplierRequestStagePresenter
{
    public function __construct(
        private RequestWorkflowTimelinePresenter $timelinePresenter,
        private RequestEffectiveStageResolver $stageResolver,
    ) {}

    /**
     * @return list<array{stage: \App\Enums\RequestStage, label: string, completed: bool, current: bool}>
     */
    public function timeline(Request $request): array
    {
        return $this->timelinePresenter->timeline($request, $this->stageResolver->effectiveStage($request));
    }
}
```

- [ ] **Step 6: Run the full presenter + portal sweep**

Run: `php artisan test --compact tests/Unit/BuyerPortal tests/Feature/BuyerPortal tests/Feature/SupplierPortal`
Expected: ALL PASS (buyer presenter tests prove delegation preserved behavior; new supplier test passes).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Services/Portal/RequestEffectiveStageResolver.php app/Services/BuyerPortal/BuyerRequestStagePresenter.php app/Services/SupplierPortal/SupplierRequestStagePresenter.php tests/Unit/BuyerPortal/BuyerRequestStagePresenterTest.php
git commit -m "refactor: shared effective-stage resolver for buyer and supplier portals"
```

---

### Task 7: Derive portal milestones from `RequestStage`

The 8-milestone list is hardcoded in `RequestWorkflowTimelinePresenter` and will drift when a stage is added; move it next to `getOrder()`/`getTabStep()` on the enum as the single source.

**Files:**
- Modify: `app/Enums/RequestStage.php` (add static method after `getOrder()`)
- Modify: `app/Services/Portal/RequestWorkflowTimelinePresenter.php` (consume it)
- Test: `tests/Unit/Erp/RequestWorkflowTimelinePresenterTest.php` (append) and `tests/Unit/Enums/RequestStageTest.php` if it exists (else the Erp test file)

- [ ] **Step 1: Write the failing test** (append to `tests/Unit/Erp/RequestWorkflowTimelinePresenterTest.php`)

```php
it('derives milestones from the enum: workflow-ordered, one per tab step', function (): void {
    $milestones = \App\Enums\RequestStage::portalMilestones();

    $orders = array_map(fn (\App\Enums\RequestStage $stage): int => $stage->getOrder(), $milestones);
    $sorted = $orders;
    sort($sorted);

    $tabSteps = array_map(fn (\App\Enums\RequestStage $stage): ?int => $stage->getTabStep(), $milestones);

    expect($orders)->toBe($sorted)
        ->and($tabSteps)->toBe([1, 2, 3, 6, 4, 5, 7, 8])
        ->and($milestones)->toHaveCount(8);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Erp/RequestWorkflowTimelinePresenterTest.php --filter="derives milestones"`
Expected: FAIL — `Call to undefined method ... portalMilestones()`

- [ ] **Step 3: Add the enum method** (after `getOrder()` in `RequestStage.php`)

```php
/**
 * Portal progress milestones in chronological workflow order — the single
 * source for RequestWorkflowTimelinePresenter. SHIPPED collapses onto
 * DELIVERED (both are tab step 8); post-delivery stages have no milestone.
 *
 * @return list<self>
 */
public static function portalMilestones(): array
{
    return [
        self::DRAFT,
        self::AWAITING_SUPPLIER_RESPONSE,
        self::PREPARING_BUYER_QUOTE,
        self::AWAITING_BUYER_CONFIRMATION,
        self::PREPARING_SUPPLIER_ORDER,
        self::GOODS_RECEIVE,
        self::AWAITING_SHIPMENT,
        self::DELIVERED,
    ];
}
```

- [ ] **Step 4: Consume it in the presenter.** In `RequestWorkflowTimelinePresenter::timeline()` replace the local `$milestones = [ ...8 cases... ];` array (and its explanatory comment) with:

```php
$milestones = RequestStage::portalMilestones();
```

- [ ] **Step 5: Run the presenter test file**

Run: `php artisan test --compact tests/Unit/Erp/RequestWorkflowTimelinePresenterTest.php`
Expected: 6 PASS

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Enums/RequestStage.php app/Services/Portal/RequestWorkflowTimelinePresenter.php tests/Unit/Erp/RequestWorkflowTimelinePresenterTest.php
git commit -m "refactor: derive portal milestones from RequestStage"
```

---

### Task 8: Zero PHPStan errors in the review-touched files

Ten pre-existing errors live in files today's commits touched. Fix them so the scoped set analyzes clean.

**Files:**
- Modify: `app/Filament/Buyer/Resources/BuyerRequestResource/RelationManagers/InvoicesRelationManager.php:56,63`
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php:155,749`
- Modify: `app/Services/Timeline/PortalTimelineSource.php:68,105,134,145,349,358`

- [ ] **Step 1: Capture the baseline failure**

Run: `php vendor/bin/phpstan analyse --no-progress --error-format=raw app/Filament/Buyer/Resources/BuyerRequestResource/RelationManagers/InvoicesRelationManager.php app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php app/Services/Timeline/PortalTimelineSource.php`
Expected: 10 errors (4× `nullsafe.neverNull`, 3× `missingType.generics`, 2× list-type, 1× return-type).

- [ ] **Step 2: Apply the fixes.**

Nullsafe (larastan proves the left side non-null — drop `?->` for `->`):
- `InvoicesRelationManager.php:56,63`: `$record->currency?->code ?? ''` → `$record->currency->code ?? ''`
- `BuyerOrdersRelationManager.php:155`: `$record?->unit_label ?? '—'` → `$record->unit_label ?? '—'` (keep the closure's `?BuyerOrderItem`-style nullability only if phpstan still requires it; re-run to confirm)
- `BuyerOrdersRelationManager.php:749`: `$this->activeInvoiceFor($record)?->amount_outstanding ?? 0` — `activeInvoiceFor` returns `?BuyerInvoice`, so if this one is flagged the fix is the surrounding expression phpstan names, not the nullsafe on a genuinely nullable value; follow the error text exactly.

`PortalTimelineSource.php`:
- Line 68: `return $entries->all();` → `return array_values($entries->all());` (satisfies the `list<TimelineEntry>` return docblock)
- Lines 105/134/145: complete the generics — `@return array{0: Builder<\Illuminate\Database\Eloquent\Model>, 1: string}` on `baseSubjectQuery()`, `@param Builder<\Illuminate\Database\Eloquent\Model> $query` on `applyRule()` and `applyWhere()`
- Lines 349/358: the `$attachments` variable — build it as a guaranteed list: `$attachments = array_values($note->getMedia(RequestNote::ATTACHMENTS_COLLECTION)->map(fn ($media): string => (string) $media->file_name)->all());` (replacing the `->values()->all()` chain phpstan cannot narrow)

- [ ] **Step 3: Re-run scoped analysis until clean**

Run: the same command as Step 1.
Expected: `[OK] No errors`

- [ ] **Step 4: Run the covering tests**

Run: `php artisan test --compact tests/Unit/Erp/PortalTimelineSourceTest.php tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`
Expected: ALL PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Buyer/Resources/BuyerRequestResource/RelationManagers/InvoicesRelationManager.php app/Filament/Resources/RequestResource/RelationManagers/BuyerOrdersRelationManager.php app/Services/Timeline/PortalTimelineSource.php
git commit -m "chore: zero phpstan errors in review-touched files"
```

---

### Task 9: Extract shared email partials and migrate the invoice template

Four hand-rolled email skeletons share the same head/body styles, 600px card, logo header cell, blue separator, bill-to block, and signature footer. Extract partials and migrate `invoice-to-buyer` as the proof.

**Files:**
- Create: `resources/views/emails/partials/email-shell-top.blade.php`
- Create: `resources/views/emails/partials/email-separator.blade.php`
- Create: `resources/views/emails/partials/email-bill-to.blade.php`
- Create: `resources/views/emails/partials/email-signature.blade.php`
- Create: `resources/views/emails/partials/email-shell-bottom.blade.php`
- Modify: `resources/views/emails/invoice-to-buyer.blade.php`
- Test: existing "renders the default invoice email template" in `tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php`

- [ ] **Step 1: Create the partials** (markup lifted verbatim from `invoice-to-buyer.blade.php`, parameterized only where templates differ):

`email-shell-top.blade.php` (params: `string $emailTitle`, `\App\Models\Team $team` — everything from `<!DOCTYPE html>` through the logo cell open; the right-hand meta cell stays in the caller):

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailTitle }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 30px 30px 20px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="40%" valign="top" style="vertical-align: top;">
                                        @if($team->getEmailLogoUrl())
                                            <img src="{{ $team->getEmailLogoUrl() }}" alt="{{ $team->getErpSettings()->company_name ?: config('app.name') }}" style="max-width: 150px; height: auto; display: block; margin-bottom: 15px;">
                                        @endif
                                    </td>
```

`email-separator.blade.php`:

```blade
<tr>
    <td style="padding: 0 30px;">
        <div style="height: 2px; background-color: #2563eb;"></div>
    </td>
</tr>
```

`email-bill-to.blade.php` (param: `$company` — a Company or null):

```blade
<tr>
    <td style="padding: 25px 30px 20px;">
        <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 8px;">{{ $company->name ?? 'Customer' }}</div>
        @if($company?->address)
            <div style="font-size: 13px; color: #6b7280; line-height: 1.6; margin-bottom: 4px;">
                {{ $company->address }}
            </div>
        @endif
        @if($company?->email)
            <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                Email: {{ $company->email }}
            </div>
        @endif
    </td>
</tr>
```

`email-signature.blade.php` (param: `\App\Models\Team $team`):

```blade
<p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-top: 25px;">
    Thank you for your business.
</p>

@if($team->getErpSettings()->email_signature)
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">
        {!! nl2br(e($team->getErpSettings()->email_signature)) !!}
    </div>
@endif
```

`email-shell-bottom.blade.php`:

```blade
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

- [ ] **Step 2: Migrate `invoice-to-buyer.blade.php`** — replace the extracted regions with includes, keeping the invoice-specific right-hand meta cell, greeting/content block, items table, payment terms, and notes exactly as they are:

```blade
@include('emails.partials.email-shell-top', ['emailTitle' => 'Invoice '.$invoice->invoice_number, 'team' => $team])
                                    <td width="60%" valign="top" align="right" style="vertical-align: top; text-align: right;">
                                        {{-- unchanged invoice meta block --}}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
@include('emails.partials.email-separator')
@include('emails.partials.email-bill-to', ['company' => $buyer])
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            {{-- unchanged greeting/content/items/payment-terms/notes blocks --}}
@include('emails.partials.email-signature', ['team' => $team])
                        </td>
                    </tr>
@include('emails.partials.email-shell-bottom')
```

Note: `email-bill-to` renders the fallback name `'Customer'` where the old inline block rendered `'Buyer'`; grep tests for `'Buyer'` assertions against this template first — if any assert the fallback, keep the partial param as `['company' => $buyer, 'fallbackName' => 'Buyer']` and render `{{ $company->name ?? $fallbackName ?? 'Customer' }}`.

- [ ] **Step 3: Run the render test**

Run: `php artisan test --compact tests/Feature/Filament/App/Resources/BuyerInvoiceActionTest.php --filter="renders the default invoice email template"`
Expected: PASS — same invoice number and greeting copy present.

- [ ] **Step 4: Visual spot check.** Render to a file and open it:

```bash
php artisan tinker --execute="file_put_contents('/tmp/invoice-email.html', view('emails.invoice-to-buyer', ['invoice' => \App\Models\BuyerInvoice::first()->load(['items','currency','buyerOrder.buyer','request']), 'content' => '', 'team' => \App\Models\Team::first()])->render());"
```

Compare against a pre-change render of the same invoice (generate it before Step 2).

- [ ] **Step 5: Pint + commit**

```bash
git add resources/views/emails/partials/email-shell-top.blade.php resources/views/emails/partials/email-separator.blade.php resources/views/emails/partials/email-bill-to.blade.php resources/views/emails/partials/email-signature.blade.php resources/views/emails/partials/email-shell-bottom.blade.php resources/views/emails/invoice-to-buyer.blade.php
git commit -m "refactor: shared email layout partials, invoice template migrated"
```

---

### Task 10: Migrate the remaining three email templates onto the shared partials

**Files:**
- Modify: `resources/views/emails/buyer-order-to-buyer.blade.php`
- Modify: `resources/views/emails/quote-to-buyer.blade.php` (actual filename may differ — find with `ls resources/views/emails/`)
- Modify: `resources/views/emails/quote-to-supplier.blade.php` (same caveat)
- Test: each template's existing render/send test (find with `grep -rln "emails.buyer-order-to-buyer\|emails.quote-to" tests/`)

For EACH template, apply the identical mechanical migration proven in Task 9:

- [ ] **Step 1: Snapshot the current render** of the template to `/tmp/<name>-before.html` using the same tinker pattern as Task 9 Step 4, with that template's own view name and variables (read the template's `@php` header for its expected variables).

- [ ] **Step 2: Replace the four regions with includes** — exactly as in Task 9 Step 2:
  - `<!DOCTYPE html>` through the logo cell → `@include('emails.partials.email-shell-top', ['emailTitle' => <the template's existing <title> expression>, 'team' => $team])`
  - the blue `height: 2px; background-color: #2563eb` row → `@include('emails.partials.email-separator')`
  - the recipient name/address/email block → `@include('emails.partials.email-bill-to', ['company' => <the template's recipient variable>])`
  - the thank-you + `email_signature` block → `@include('emails.partials.email-signature', ['team' => $team])`
  - trailing `</table></td></tr></table></body></html>` → `@include('emails.partials.email-shell-bottom')`

  Keep every template-specific block (meta cell, greeting, items tables, terms) byte-identical. If a template's markup deviates from the partial (different padding, extra row), DO NOT force it — leave that region inline and note the deviation in the commit message.

- [ ] **Step 3: Render again and diff** `/tmp/<name>-before.html` vs the new render. Expected: byte-identical, or differences limited to the deliberate fallback-name change.

- [ ] **Step 4: Run that template's tests.** Expected: PASS.

- [ ] **Step 5: Commit per template**

```bash
git add resources/views/emails/<template>.blade.php
git commit -m "refactor: migrate <template> email onto shared partials"
```

---

### Task 11 (optional, recommended): PHPStan baseline so the type gate turns green

`composer test:types` currently fails with 408 repo-wide errors, making the type gate useless for catching NEW errors.

**Files:**
- Create: `phpstan-baseline.neon` (generated)
- Modify: `phpstan.neon` (include the baseline)

- [ ] **Step 1: Generate**

Run: `php vendor/bin/phpstan analyse --no-progress --generate-baseline`
Expected: `phpstan-baseline.neon` written with ~398 ignored errors (408 minus Task 8's 10).

- [ ] **Step 2: Include it.** Add to `phpstan.neon` under `includes:` (create the key if absent):

```neon
includes:
    - phpstan-baseline.neon
```

- [ ] **Step 3: Verify the gate is green and still bites**

Run: `php vendor/bin/phpstan analyse --no-progress`
Expected: `[OK] No errors`. Then temporarily add `$x = 1; $x->foo();` to any service, re-run, confirm it errors, revert.

- [ ] **Step 4: Commit**

```bash
git add phpstan.neon phpstan-baseline.neon
git commit -m "chore: phpstan baseline so new type errors gate CI"
```

---

## Final Verification (after all tasks)

- [ ] Full affected sweep: `php artisan test --compact tests/Unit/BuyerPortal tests/Unit/Erp tests/Unit/Enums tests/Feature/BuyerPortal tests/Feature/SupplierPortal tests/Feature/Filament/App/Resources tests/Feature/Erp/RequestNoteTest.php` — ALL PASS
- [ ] `vendor/bin/pint --test` on all touched files — PASS
- [ ] Scoped phpstan (Task 8 command) — 0 errors
- [ ] Push: `git push`

## Explicitly Out of Scope (deliberate)

- **`BuyerInvoiceStatusPresenter` altitude** (SENT→"Received" remap lives in one presenter used by one column): correct today; revisit only when a second buyer surface renders invoice status.
- **Buyer/supplier items-table blade merge**: the two tables share shell markup but differ in data source (record vs state), columns, and item models; a forced merge trades visible duplication for a config-driven partial that is harder to read. Revisit if a third portal table appears.
- **The 398 baselined phpstan errors**: burn down opportunistically per-file when touching those files, not as a big-bang.

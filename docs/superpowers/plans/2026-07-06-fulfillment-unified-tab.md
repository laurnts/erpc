# Unified "Fulfillment" Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the goods-only "Inbound Shipments" tab on the Request view page with a single "Fulfillment" tab (a Filament `RelationGroup`) that renders the goods (Shipments) and/or services (Acceptance Reports) channels applicable to the request.

**Architecture:** Native Filament v5 `RelationGroup` groups the two existing relation managers under one tab. The group renders viewable children stacked; `RelationGroup::getManagers()` filters children by their existing `canViewForRecord()` gates, so goods-only shows only Shipments, services-only only Acceptance Reports, mixed shows both. The group tab's badge/gating is reused from an existing child's `getTabComponent()` — no shared-trait changes. Fixes two latent bugs: acceptance reports were never rendered on the view page (bug A), and the goods tab showed for services-only requests because the page's raw-list override bypasses `canViewForRecord` (bug B).

**Tech Stack:** Laravel 12, Filament v5, Livewire v4, Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-06-fulfillment-unified-tab-design.md`

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`.
- Classes are `final` by default; comparisons use `===` / `!==`; all methods have explicit return types.
- Run `vendor/bin/pint --dirty` before each commit.
- Tests are Pest 4 feature tests in `tests/Feature/...`; run with `php artisan test --compact --filter=<name>`.
- Tab title copy: group tab = `"Fulfillment"`; goods sub-section = `"Shipments"` (renamed from `"Inbound Shipments"`); services sub-section = `"Acceptance Reports"` (unchanged).
- Deep-link canonical key = `"fulfillment"`; do NOT add legacy `"shipments"`/`"acceptanceReports"` aliases (they corrupt the positional index map).
- Item types created in tests via `RequestItem::factory()->for($request)->create(['item_type' => ItemType::GOODS])` (or `ItemType::SERVICE`).

---

### Task 1: Register the Fulfillment RelationGroup with channel visibility

**Files:**
- Modify: `app/Filament/Resources/RequestResource/Pages/ViewRequest.php` (imports; `getRelationManagers()` ~line 396-408; add `fulfillmentTab()` method)
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/ShipmentsRelationManager.php:50,59-62` (title rename)
- Test: `tests/Feature/Filament/App/Resources/FulfillmentTabTest.php` (create)

**Interfaces:**
- Produces: a `RelationGroup` labelled `"Fulfillment"` in `ViewRequest::getRelationManagers()` at index 6, containing `ShipmentsRelationManager::class` then `AcceptanceReportsRelationManager::class`; and `private function fulfillmentTab(Request $record): \Filament\Schemas\Components\Tabs\Tab`.
- Consumes: `ShipmentsRelationManager::canViewForRecord()` (`requiresShipments()`), `AcceptanceReportsRelationManager::canViewForRecord()` (`usesAcceptanceReports()`), and either manager's static `getTabComponent(Model, string): Tab`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/App/Resources/FulfillmentTabTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\AcceptanceReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager;
use App\Models\Company;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationGroup;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();
    $buyer = Company::factory()->buyer()->for($this->team)->create();
    $this->makeRequest = fn (): Request => Request::factory()->for($this->team)->recycle($buyer)->create();
});

function fulfillmentGroup(): RelationGroup
{
    $group = collect(app(ViewRequest::class)->getRelationManagers())
        ->first(fn ($m): bool => $m instanceof RelationGroup && $m->getLabel() === 'Fulfillment');

    expect($group)->toBeInstanceOf(RelationGroup::class);

    return $group;
}

/** @return array<string> */
function visibleFulfillmentManagers(Request $request): array
{
    return array_values(fulfillmentGroup()->ownerRecord($request)->pageClass(ViewRequest::class)->getManagers());
}

it('registers a Fulfillment group at index 6', function (): void {
    $managers = app(ViewRequest::class)->getRelationManagers();

    expect($managers[6])->toBeInstanceOf(RelationGroup::class)
        ->and($managers[6]->getLabel())->toBe('Fulfillment');
});

it('shows only the goods channel for a goods-only request', function (): void {
    $request = ($this->makeRequest)();
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::GOODS]);

    expect(visibleFulfillmentManagers($request))
        ->toContain(ShipmentsRelationManager::class)
        ->not->toContain(AcceptanceReportsRelationManager::class);
});

it('shows only the services channel for a services-only request', function (): void {
    $request = ($this->makeRequest)();
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::SERVICE]);

    expect(visibleFulfillmentManagers($request))
        ->toContain(AcceptanceReportsRelationManager::class)
        ->not->toContain(ShipmentsRelationManager::class);
});

it('shows both channels for a mixed request', function (): void {
    $request = ($this->makeRequest)();
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::GOODS]);
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::SERVICE]);

    expect(visibleFulfillmentManagers($request))
        ->toContain(ShipmentsRelationManager::class)
        ->toContain(AcceptanceReportsRelationManager::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FulfillmentTabTest`
Expected: FAIL — no `RelationGroup` labelled "Fulfillment" (`$managers[6]` is `ShipmentsRelationManager::class`).

- [ ] **Step 3: Rename the Shipments tab title**

In `app/Filament/Resources/RequestResource/RelationManagers/ShipmentsRelationManager.php`, change the two title strings from `'Inbound Shipments'` to `'Shipments'`:

```php
    protected static ?string $title = 'Shipments';
```

```php
    protected static function getBaseTabTitle(): string
    {
        return 'Shipments';
    }
```

- [ ] **Step 4: Register the group and add the tab closure in ViewRequest**

Add imports near the other relation-manager imports (`AcceptanceReportsRelationManager` is not yet imported):

```php
use App\Filament\Resources\RequestResource\RelationManagers\AcceptanceReportsRelationManager;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Schemas\Components\Tabs\Tab;
```

In `getRelationManagers()`, replace the `ShipmentsRelationManager::class,` line with:

```php
            RelationGroup::make('Fulfillment', [
                ShipmentsRelationManager::class,
                AcceptanceReportsRelationManager::class,
            ])->tab(fn (Request $record): Tab => $this->fulfillmentTab($record)),
```

Add this private method to the class:

```php
    /**
     * Build the Fulfillment group tab by reusing an existing channel manager's
     * stage badge + gating. Prefer the goods manager when goods are present so
     * its unapproved-Goods-Receive tab-disable is preserved; otherwise use the
     * clean services tab. Relabelled to "Fulfillment".
     */
    private function fulfillmentTab(Request $record): Tab
    {
        $source = $record->requiresShipments()
            ? ShipmentsRelationManager::class
            : AcceptanceReportsRelationManager::class;

        return $source::getTabComponent($record, static::class)->label('Fulfillment');
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=FulfillmentTabTest`
Expected: PASS (4 passing).

- [ ] **Step 6: Smoke-test the view page renders with the group**

Add this test to the same file, then run the filter again:

```php
it('renders the view page with a Fulfillment tab', function (): void {
    $request = ($this->makeRequest)();
    RequestItem::factory()->for($request)->create(['item_type' => ItemType::GOODS]);

    Livewire\Livewire::test(ViewRequest::class, ['record' => $request->getKey()])
        ->assertOk()
        ->assertSee('Fulfillment');
});
```

Run: `php artisan test --compact --filter=FulfillmentTabTest`
Expected: PASS (5 passing).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Resources/RequestResource/Pages/ViewRequest.php app/Filament/Resources/RequestResource/RelationManagers/ShipmentsRelationManager.php tests/Feature/Filament/App/Resources/FulfillmentTabTest.php
git commit -m "feat: unify goods+services fulfillment into one Fulfillment tab"
```

---

### Task 2: Deep-link key + stage auto-advance rename

**Files:**
- Modify: `app/Filament/Resources/RequestResource/Pages/ViewRequest.php` (`RELATION_MANAGER_MAP` ~line 53-62)
- Modify: `app/Enums/RequestStage.php` (`fromRelationManagerKey()` ~line 152-165)
- Test: `tests/Feature/Filament/App/Resources/FulfillmentTabTest.php` (append)

**Interfaces:**
- Consumes: `ViewRequest::relationManagerIndexForKey()`, `ViewRequest::getRelationManagerKeyFromIndex()` (existing), `RequestStage::fromRelationManagerKey()`.
- Produces: canonical key `"fulfillment"` mapping to index `6` and stage `AWAITING_SHIPMENT`.

- [ ] **Step 1: Write the failing test**

Append to `FulfillmentTabTest.php`:

```php
it('maps the fulfillment key to index 6 both directions', function (): void {
    expect(ViewRequest::relationManagerIndexForKey('fulfillment'))->toBe('6');

    $page = app(ViewRequest::class);
    $reflect = new ReflectionMethod($page, 'getRelationManagerKeyFromIndex');
    $reflect->setAccessible(true);
    expect($reflect->invoke($page, 6))->toBe('fulfillment');
});

it('auto-advances to the shipment stage for the fulfillment key', function (): void {
    expect(App\Enums\RequestStage::fromRelationManagerKey('fulfillment'))
        ->toBe(App\Enums\RequestStage::AWAITING_SHIPMENT);
    expect(App\Enums\RequestStage::fromRelationManagerKey('shipments'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FulfillmentTabTest`
Expected: FAIL — `relationManagerIndexForKey('fulfillment')` is null and `fromRelationManagerKey('fulfillment')` is null (map still uses `'shipments'`).

- [ ] **Step 3: Rename the key in RELATION_MANAGER_MAP**

In `ViewRequest.php` `RELATION_MANAGER_MAP`, change:

```php
        'shipments' => 6,
```

to:

```php
        'fulfillment' => 6,
```

- [ ] **Step 4: Rename the arm in fromRelationManagerKey**

In `app/Enums/RequestStage.php` `fromRelationManagerKey()`, change:

```php
            'shipments' => self::AWAITING_SHIPMENT,
```

to:

```php
            'fulfillment' => self::AWAITING_SHIPMENT,
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=FulfillmentTabTest`
Expected: PASS (7 passing).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Resources/RequestResource/Pages/ViewRequest.php app/Enums/RequestStage.php tests/Feature/Filament/App/Resources/FulfillmentTabTest.php
git commit -m "feat: use 'fulfillment' as the canonical deep-link/stage key"
```

---

### Task 3: Add Fulfillment to the stage-tab badge matrix

**Files:**
- Modify: `tests/Feature/Filament/App/Resources/StageTabBadgeMatrixTest.php` (docblock lines 27-38; import; `$tabs` array line 82-90; comment line 92-93)

**Interfaces:**
- Consumes: `ShipmentsRelationManager::getTabComponent($record, ViewRequest::class)->getBadge()` (the source the group reuses for goods/mixed).

- [ ] **Step 1: Add the import and the Fulfillment row**

Add the import with the others:

```php
use App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager;
```

In the `$tabs` array, add after the `CompletionReportsRelationManager` row:

```php
        ['class' => ShipmentsRelationManager::class, 'stage' => RequestStage::AWAITING_SHIPMENT, 'pos' => 6],
```

- [ ] **Step 2: Rewrite the stale docblock and comment**

Replace the docblock paragraph (lines 35-37) that begins "Shipments is intentionally excluded" with:

```php
 * The Fulfillment tab (RelationGroup) reuses ShipmentsRelationManager's
 * getTabComponent(), which delegates to the shared HasRequestStageTab stage
 * logic — so it is included here at tab position 6.
```

Replace the inline comment at lines 92-93 ("Shipments sits at pos 6 … intentionally excluded") with:

```php
    // Fulfillment sits at pos 6; shipped/delivered are past it; invoiced+ are past every tab.
```

- [ ] **Step 3: Run the matrix to verify it passes**

Run: `php artisan test --compact --filter=StageTabBadgeMatrixTest`
Expected: PASS — the new row's badge is ✓/●/null per stage order (existing 7 rows unchanged). The `beforeEach` already satisfies QE/PNL/accepted-quote gates and creates no goods-receive media, so the badge reflects pure stage progression.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Filament/App/Resources/StageTabBadgeMatrixTest.php
git commit -m "test: cover the Fulfillment tab badge in the stage matrix"
```

---

### Task 4: Update the information-flow widget key + copy

**Files:**
- Modify: `app/Filament/Widgets/RequestInformationFlowWidget.php` (match arm line 79; index map line 97; `getShipmentsInformationFlow()` line 227 + copy lines 220,230,225)
- Modify: `resources/views/filament/widgets/request-information-flow-widget.blade.php:11`
- Test: `tests/Feature/Filament/App/Resources/FulfillmentTabTest.php` (append)

**Interfaces:**
- Produces: `RequestInformationFlowWidget::getFulfillmentInformationFlow(): string` (renamed from `getShipmentsInformationFlow`).

- [ ] **Step 1: Write the failing test**

Append to `FulfillmentTabTest.php`:

```php
it('provides fulfillment flow copy for the widget', function (): void {
    $widget = new App\Filament\Widgets\RequestInformationFlowWidget();

    expect($widget->getFulfillmentInformationFlow())->toContain('Fulfillment');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="provides fulfillment flow copy"`
Expected: FAIL — `getFulfillmentInformationFlow()` does not exist.

- [ ] **Step 3: Rename the method, its match arm, the index-map key, and the copy**

In `RequestInformationFlowWidget.php`:

Line 79 match arm — change `'shipments' => $this->getShipmentsInformationFlow(),` to:

```php
            'fulfillment' => $this->getFulfillmentInformationFlow(),
```

Line 97 index map — change `'shipments' => 6,` to:

```php
            'fulfillment' => 6,
```

Rename the method (line 227) and update its copy (lines 225, 230) and the "Next" pointer on line 220:

```php
    /**
     * Get information flow text for the Fulfillment tab.
     */
    public function getFulfillmentInformationFlow(): string
    {
        return <<<'MARKDOWN'
**Step 7: Fulfillment**

Record fulfillment for each channel of this request:

- **Goods** — create inbound shipments and mark them delivered.
- **Services** — file acceptance reports for completed service items.

For a mixed request, both channels appear here. Goods require approved Goods Receive documents first.
MARKDOWN;
    }
```

Line 220 — change `**Next:** approved documents unlock Inbound Shipments.` to:

```php
**Next:** approved documents unlock Fulfillment (goods shipments).
```

In `resources/views/filament/widgets/request-information-flow-widget.blade.php`, line 11 — change the key `'inbound shipments' => ...getShipmentsInformationFlow()` to:

```php
            'fulfillment' => \Illuminate\Support\Str::markdown($this->getFulfillmentInformationFlow()),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter="provides fulfillment flow copy"`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Widgets/RequestInformationFlowWidget.php resources/views/filament/widgets/request-information-flow-widget.blade.php tests/Feature/Filament/App/Resources/FulfillmentTabTest.php
git commit -m "feat: fulfillment flow guide copy + key"
```

---

### Task 5: Remove the dead Shipments data-badge methods

**Files:**
- Modify: `app/Filament/Resources/RequestResource/RelationManagers/ShipmentsRelationManager.php:830-848` (delete `getBadge()` and `getBadgeColor()`)

**Interfaces:** none produced; removes dead code confirmed unused (the tab badge comes from `getTabComponent()` via `HasRequestStageTab`, which never calls these).

- [ ] **Step 1: Delete the two methods**

Remove the entire `public static function getBadge(...)` and `public static function getBadgeColor(...)` blocks (the delivered-shipment data badge). If `use App\Enums\ShipmentStatus;` becomes unused after removal, delete that import too (check for other `ShipmentStatus` references in the file first).

- [ ] **Step 2: Verify the badge behavior is unchanged (stage badge, not data badge)**

Run: `php artisan test --compact --filter=StageTabBadgeMatrixTest`
Expected: PASS — the Fulfillment/Shipments badge still resolves through the stage logic.

- [ ] **Step 3: Run the fulfillment + view test files to confirm green**

Run: `php artisan test --compact --filter=FulfillmentTabTest`
Then: `php artisan test --compact tests/Feature/Filament/App/Resources/RequestResourceViewTest.php`
Expected: PASS for both.

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Resources/RequestResource/RelationManagers/ShipmentsRelationManager.php
git commit -m "refactor: drop dead Shipments data-badge methods"
```

---

### Task 6: Full-suite regression sweep

**Files:** none (verification only).

- [ ] **Step 1: Run the fulfillment- and request-related suites**

Run:
```bash
php artisan test --compact tests/Feature/Filament/App/Resources tests/Feature/Erp/ItemLevelFulfillmentTest.php tests/Feature/Erp/DerivedFulfillmentCompletionTest.php tests/Feature/CustomerPortal/CustomerPortalTest.php
```
Expected: PASS. Investigate any failure referencing "Inbound Shipments", tab indices, or `activeRelationManager`.

- [ ] **Step 2: Static analysis + lint**

Run: `composer test:types` and `vendor/bin/pint --dirty`
Expected: no new errors.

- [ ] **Step 3: Confirm and report**

If all green, the feature is complete. Report the tab now reads "Fulfillment", services/mixed requests expose Acceptance Reports (bug A fixed), and services-only requests no longer show a goods shipments surface (bug B fixed).

## Self-Review

- **Spec coverage:** §1 group registration → Task 1; §2 visibility → Task 1 tests; §3 titles → Task 1 (tab titles), internal copy deferred per spec; §4 badge reuse → Task 1 `fulfillmentTab()` + Task 3 matrix; §5 deep-link/auto-advance + widget map → Tasks 2 & 4; §6 dead-code → Task 5; §7 spike → Task 1 Step 6 smoke test + Task 2 index tests; testing section → Tasks 1-6. Portal non-goal → untouched (Task 6 confirms green). Goods-receive-disable preservation → carried by Task 1 `fulfillmentTab()` choosing the Shipments source when goods present.
- **Placeholder scan:** none — every step has concrete code/commands.
- **Type consistency:** `fulfillmentTab(Request): Tab`, `getFulfillmentInformationFlow(): string`, key `'fulfillment'`, index `6`, stage `AWAITING_SHIPMENT` used consistently across tasks.

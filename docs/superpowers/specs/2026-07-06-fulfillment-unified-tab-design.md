# Unified "Fulfillment" Tab — Design

Date: 2026-07-06
Status: Draft (revised after multi-agent spec review)

## Problem

The Request detail page's fulfillment surface is goods-biased and has two real bugs.

1. **The goods fulfillment tab is named "Inbound Shipments."** `ShipmentsRelationManager`
   sets `$title = 'Inbound Shipments'`. The word "shipment" is goods-only and directional,
   so it cannot honestly cover a services or mixed deal.

2. **The services fulfillment channel is never rendered (bug A).**
   `AcceptanceReportsRelationManager` exists (relationship `acceptanceReports`, stage
   `AWAITING_SHIPMENT`, gated by `usesAcceptanceReports()` → `hasServiceItems()`), but it
   is only registered in `RequestResource::getRelations()`. There is **no Edit page**, and
   `ViewRequest` overrides `getRelationManagers()` with an explicit raw list that omits it.
   So a services-only or mixed request has **no way to record acceptance reports** from the
   request view.

3. **The goods tab shows even when there are no goods (bug B).** Filament's framework
   `getRelationManagers()` (`vendor/.../HasRelationManagers.php:43-51`) filters managers by
   `canViewForRecord`. **`ViewRequest` overrides that method with a raw list, bypassing the
   filter.** Consequently `ShipmentsRelationManager::canViewForRecord()` (→
   `requiresShipments()`) is never consulted, and the "Inbound Shipments" tab renders for
   **every** request type — including services-only deals that have no goods.

   Actual current view-page behavior:

   | Request type | "Inbound Shipments" tab | "Acceptance Reports" tab |
   |---|---|---|
   | Goods-only | shows (correct) | — |
   | Services-only | **shows (wrong — bug B)** | **never (bug A)** |
   | Mixed | shows | **never (bug A)** |

4. **Mixed goods+service deals are core business**, so the fulfillment surface must
   represent both channels under one coherent concept.

## Goal

Replace the standalone goods-only "Inbound Shipments" tab with a single **"Fulfillment"**
tab that renders the applicable fulfillment channel(s) — goods (Shipments) and services
(Acceptance Reports) — and, in doing so, fix bugs A and B.

## Approach

Use Filament v5's native **`RelationGroup`** to group the two existing relation managers
under one tab. Verified against `vendor/filament` source during review:

- **Rendering is stacked.** For a `RelationGroup`, Filament maps each viewable child into
  the group tab's `->schema()` as a stacked Livewire component
  (`HasRelationManagers.php:139-152`). So a **mixed** request shows the Shipments table and
  the Acceptance Reports table **stacked, both visible at once** under one "Fulfillment"
  tab. (This is the "both at once" layout originally preferred; the native construct
  delivers it with no custom Livewire.)
- **Per-channel visibility is honored.** `RelationGroup::getManagers()` filters children by
  `canViewForRecord`, so goods-only shows only Shipments, services-only only Acceptance
  Reports, mixed shows both — **fixing bug B** (the group finally respects
  `requiresShipments()` / `usesAcceptanceReports()`).
- **No model, table, or create-flow changes.** Both managers are reused untouched.

Rejected alternatives: a bespoke stacked wrapper (needs custom nested-Livewire +
mount-gate rework — over-engineered, since `RelationGroup` already stacks) and renaming the
goods tab only (leaves bugs A and B).

## Detailed design

### 1. Group registration (`ViewRequest::getRelationManagers()`)

Replace the standalone `ShipmentsRelationManager::class` entry (index 6) with a group,
guarded so a no-items draft doesn't render an empty tab:

```php
use Filament\Resources\RelationManagers\RelationGroup;

// ...inside the returned list, at the former Shipments position:
...($record->hasGoodsItems() || $record->hasServiceItems()
    ? [RelationGroup::make('Fulfillment', [
        ShipmentsRelationManager::class,          // goods
        AcceptanceReportsRelationManager::class,  // services
    ])->tab(fn (Request $r): Tab => $this->fulfillmentTab($r))]
    : []),
```

Position is unchanged (between Buyer Orders and Completion Reports), so Completion Reports
keeps index 7. Because the guard only drops the group for a request with **no items at
all**, and the group's children self-filter by `canViewForRecord`, the empty-tab edge case
(reviewer-flagged) cannot occur for any real fulfillment-stage request.

### 2. Channel visibility (from existing `canViewForRecord`, no new logic)

| Request type | Shipments table | Acceptance Reports table |
|---|---|---|
| Goods-only | renders | hidden |
| Services-only | hidden (**fixes bug B**) | renders (**fixes bug A**) |
| Mixed | renders | renders (stacked) |

### 3. Titles & copy (lean cut)

- Group tab title: **"Fulfillment"**.
- Shipments section heading: `$title` / `getBaseTabTitle()` **"Inbound Shipments" →
  "Shipments"** (the "inbound" is redundant once it lives inside a Fulfillment group).
- Acceptance Reports section heading: **"Acceptance Reports"** (unchanged).
- **Deferred (not in this change):** internal notification / empty-state wording that
  mentions "Inbound Shipments" (e.g. `ShipmentsRelationManager::mount()` notification,
  `GoodsReceiveRelationManager` empty state). Left for a follow-up copy pass to keep this
  diff structural.

### 4. Group tab badge — reuse, do NOT extract

The group tab needs the same stage ✓/● badge and access-gating the other stage tabs carry.
**Do not extract the badge logic out of `HasRequestStageTab`** (that trait is shared by 7
tabs; rewiring it is churn for no gain). Instead, the group's `->tab()` closure **reuses an
existing child's `getTabComponent()` output and relabels it**:

```php
private function fulfillmentTab(Request $record): Tab
{
    // Prefer the goods manager when goods are present: its getTabComponent()
    // includes the unapproved-Goods-Receive tab-disable in addition to the shared
    // stage badge. Services-only falls back to the clean acceptance-reports tab.
    $source = $record->requiresShipments()
        ? ShipmentsRelationManager::class
        : AcceptanceReportsRelationManager::class;

    return $source::getTabComponent($record, static::class)->label('Fulfillment');
}
```

This preserves, for free:
- the shared stage badge (✓ / ● / null) from `HasRequestStageTab::getTabComponent()`;
- the QE / PNL / accepted-quote access-gating from the trait;
- **the unapproved-Goods-Receive tab-disable** that lives only in
  `ShipmentsRelationManager::getTabComponent()` — otherwise silently lost when the group
  tab replaces the child's tab (reviewer-flagged). Using the Shipments source when goods
  are present keeps it.

**Badge semantics (corrected).** The ✓ is **purely stage progression** — it appears once
the request has advanced past `AWAITING_SHIPMENT`, exactly like the other seven tabs. It
does **not** assert that every channel's work is complete: the both-channels
`isFulfilled()` gate is enforced only on the transition to `COMPLETED`
(`Request.php:471,510`; `RequestObserver.php:91`), not past `AWAITING_SHIPMENT`. The prior
draft's claim that a stage ✓ "means every channel is complete" was **false** and is
removed. (A true "both channels done" indicator, if ever wanted, would derive from
`isFulfilled()` / `fulfillmentStatusLabel()` — out of scope here.)

### 5. Deep-linking & stage auto-advance (single key, no aliases)

- **`ViewRequest::RELATION_MANAGER_MAP`** — rename the `'shipments' => 6` entry to
  `'fulfillment' => 6` (Completion Reports stays 7). **Do not add legacy `'shipments'` /
  `'acceptanceReports'` aliases:** nothing in the app emits those query keys, the
  acceptance-reports view URL never worked, and — critically — the two map consumers use
  different semantics (`relationManagerIndexForKey()` reads map *values*;
  `getRelationManagerKeyFromIndex()` uses *positional* `array_keys()`), so an extra key at
  index 6 would shift `completionReports` off index 7 and corrupt the reverse lookup
  (reviewer-flagged). One canonical key only.
- **`RequestStage::fromRelationManagerKey()`** — add `'fulfillment' => AWAITING_SHIPMENT`
  (needed so tab-switch auto-advance still targets the shipment stage). Remove the now-dead
  `'shipments'` arm.
- **`RequestInformationFlowWidget`** carries its **own duplicate** index map and a
  `match()` arm keyed on `'shipments'` (`RequestInformationFlowWidget.php:79,97`). Rename
  both to `'fulfillment'` in the same change (a second source of truth that otherwise goes
  stale and renders an empty flow guide). Update the "Step 7: Inbound Shipments" heading to
  "Fulfillment" and have the flow copy mention both channels. (Deduplicating the two maps
  is a separate cleanup — not folded in here.)
- Child `mount()` redirects (Shipments → `goodsReceive`; trait gates → other tabs) are
  unchanged and still fire from within the stacked child components.

### 6. Dead-code cleanup

`ShipmentsRelationManager::getBadge()` / `getBadgeColor()` (the delivered-shipment data
badge) are already dead — `getTabComponent()` is overridden by `HasRequestStageTab` and
never calls them (review corrected the earlier belief that Shipments used a data badge).
After the group change the manager is a stacked child, so they remain dead. Remove them to
prevent a second, divergent badge source.

### 7. Spike first (de-risk indexing)

First implementation task is a small spike confirming, in the running app: (a) the group
renders one "Fulfillment" tab with both child tables stacked for a mixed request; (b) the
group tab is addressable at integer index 6 for `?activeRelationManager` deep-linking given
the sequential-literal override; (c) `updatedActiveRelationManager(6)` fires and
auto-advances an eligible request to `AWAITING_SHIPMENT`. If indexing differs, adjust
`RELATION_MANAGER_MAP` / `relationManagerIndexForKey`.

## Non-goals

- No change to `Shipment` / `AcceptanceReport` models, tables, create flows, or data.
- No merge of the two into a single table; no custom nested-Livewire wrapper.
- No change to `GoodsReceive` (`GOODS_RECEIVE`) or `CompletionReports` (`DELIVERED`) tabs.
- **Customer portal is out of scope.** `App\Filament\Customer\...\ShipmentsRelationManager`
  is a separate class (relationship `shipments`, title already "Shipments", OUTBOUND-only,
  no stage badge) registered only on `CustomerRequestResource`; it is untouched, and
  renaming the staff sub-heading to "Shipments" does not collide with it. The parallel
  buyer-portal services-fulfillment gap (no acceptance-reports surface for buyers) is
  **deliberately deferred**, not fixed here.
- No new "deliverables"/"fulfillment" data concept — "Fulfillment" is presentation-layer
  vocabulary over the existing channels.

## Testing

- **Stage-tab badge matrix** (`tests/Feature/.../StageTabBadgeMatrixTest.php`): add a
  Fulfillment row at tab position 6 (verified: the existing 7 rows' expected ✓/●/null need
  **no** changes across all stages). The row resolves its badge through the group's source
  — `ShipmentsRelationManager::getTabComponent($record, ViewRequest::class)->getBadge()` for
  a goods/mixed record — asserting stage ✓/●/null. Rewrite the stale docblock/comment that
  says "Shipments is intentionally excluded … data-based badge" (both false) to state that
  Fulfillment now carries the shared stage badge.
- **Channel visibility**: goods-only renders only the Shipments table; services-only only
  Acceptance Reports (**and no shipments surface — bug B regression guard**); mixed renders
  both stacked.
- **Services-gap regression (bug A)**: a services-only request can reach and create an
  acceptance report from the Fulfillment tab.
- **Goods-receive tab-disable preserved**: with unapproved Goods-Receive docs, the
  Fulfillment tab is disabled (asserts the §4 Shipments-source reuse).
- **Deep-link**: `?activeRelationManager=fulfillment` lands on the Fulfillment tab (assert
  the resolved active index/component, not merely no-error).
- **Auto-advance**: `updatedActiveRelationManager(6)` →
  `getRelationManagerKeyFromIndex(6) === 'fulfillment'` →
  `fromRelationManagerKey('fulfillment') === AWAITING_SHIPMENT` → advances an eligible
  request (mirror existing `updatedActiveRelationManager` coverage).
- **`RequestResourceViewTest`**: initial render asserts **"Fulfillment"** in the tab bar
  (the old `assertSee('Shipments')` on load breaks — "Shipments" now renders only inside
  the group).
- **Portal untouched**: existing customer-portal tests remain green.

## Rollout / risk

- Intended behavior changes: acceptance reports become visible for services/mixed (bug A),
  and the goods tab no longer shows on services-only requests (bug B).
- Low blast radius: no schema/model changes; the change is tab composition, a small
  `->tab()` closure that reuses existing child logic, deep-link key rename (+ the widget's
  duplicate), copy, and dead-code removal. The §7 spike de-risks the only real unknown.

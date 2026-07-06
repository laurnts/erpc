# Unified "Fulfillment" Tab — Design

Date: 2026-07-06
Status: Draft (pending user review)

## Problem

The Request detail page presents fulfillment as goods-only, and the services
equivalent is unreachable.

1. **The goods fulfillment tab is named "Inbound Shipments."** `ShipmentsRelationManager`
   sets `$title = 'Inbound Shipments'` and is visible only when the request has goods
   items (`canViewForRecord()` → `requiresShipments()` → `hasGoodsItems()`). The word
   "shipment" is goods-only and directional, so it cannot honestly cover a services or
   mixed deal.

2. **The services fulfillment channel is dead-wired.** `AcceptanceReportsRelationManager`
   exists (relationship `acceptanceReports`, stage `AWAITING_SHIPMENT`, visible when
   `usesAcceptanceReports()` → `hasServiceItems()`), but it is only registered in
   `RequestResource::getRelations()`. There is **no Edit page**, and `ViewRequest`
   overrides `getRelationManagers()` with an explicit list that omits it. As a result a
   **services-only request has no tab on the view page to record acceptance reports**, and
   a mixed request shows only the goods side.

   Current view-page fulfillment tabs (`ViewRequest::getRelationManagers()`):

   | Request type | "Inbound Shipments" | "Acceptance Reports" |
   |---|---|---|
   | Goods-only | shows | — |
   | Services-only | — | **never rendered** |
   | Mixed | shows | **never rendered** |

3. **Mixed goods+service deals are core business** (not edge cases), so the fulfillment
   surface must represent both channels under one coherent concept.

## Goal

Replace the standalone goods-only "Inbound Shipments" tab with a single **"Fulfillment"**
tab that hosts both fulfillment channels — goods (Shipments) and services
(Acceptance Reports) — showing whichever channel(s) apply to the request. Fix the
services gap as part of the same change.

## Non-goals

- No change to the `Shipment` / `AcceptanceReport` models, tables, create flows, or their
  underlying data. They remain the precise per-channel records.
- No merge of the two into a single table or a custom stacked component. The user chose
  the clean, lean, idiomatic path.
- No change to `GoodsReceive` (stage `GOODS_RECEIVE`) or `CompletionReports`
  (stage `DELIVERED`) tabs. They are separate stages and out of scope.
- No new "deliverables"/"fulfillment" data concept. "Fulfillment" is presentation-layer
  vocabulary layered over the existing channels (see naming decision memory).

## Approach

Use Filament v5's native **`RelationGroup`** to group the two existing relation managers
under one tab. This is the idiomatic construct for "several relation managers, one tab,"
requires no custom Livewire, and reuses both managers untouched — their tables, create
actions, and mount-time gating all keep working because each still renders in its own
component context.

Two alternatives were considered and rejected:

- **Stacked custom wrapper (both tables visible at once).** Requires a bespoke
  nested-Livewire component and reworking each manager's mount-time page-redirect gates
  into inline section locks. Rejected as over-engineered for the value.
- **Rename the goods tab only.** Leaves the services channel dead-wired and the
  "Fulfillment" label goods-only. Rejected — defeats the goods+services+mixed goal.

## Detailed design

### 1. Group registration

In `ViewRequest::getRelationManagers()`, replace the standalone
`ShipmentsRelationManager::class` entry (currently index 6) with a `RelationGroup`:

```php
use Filament\Resources\RelationManagers\RelationGroup;

RelationGroup::make('Fulfillment', [
    ShipmentsRelationManager::class,          // goods    → requiresShipments()
    AcceptanceReportsRelationManager::class,  // services → usesAcceptanceReports()
])->tab(fn (Request $record): Tab => static::fulfillmentTabComponent($record))
```

Position is unchanged: the group sits where Shipments was (between Buyer Orders and
Completion Reports), so the Completion Reports tab keeps its slot.

### 2. Channel visibility (no new logic)

The two managers' existing `canViewForRecord()` gates route the sub-navigation for free:

| Request type | Shipments sub-item | Acceptance Reports sub-item |
|---|---|---|
| Goods-only | shows | hidden |
| Services-only | hidden | **shows (fix)** |
| Mixed | shows | **shows (fix)** |

When only one channel applies, the group renders that single channel; when both apply
(mixed), the group shows both as sub-navigation, one visible at a time.

### 3. Tab title & sub-labels (copy)

- Group tab title: **"Fulfillment"**.
- Shipments sub-item: rename `getBaseTabTitle()` / `$title` from **"Inbound Shipments"** →
  **"Shipments"** ("inbound" is redundant inside a Fulfillment group).
- Acceptance Reports sub-item: **"Acceptance Reports"** (unchanged).

### 4. Tab badge (✓ / ● / disabled)

The group tab needs the same stage-completion badge the other stage tabs carry. Both
child managers are anchored to `AWAITING_SHIPMENT`, so the group badge is that stage's
badge.

- Extract the stage badge/gating computation currently inside
  `HasRequestStageTab::getTabComponent()` into a reusable static helper keyed by a stage
  (e.g. `RequestStageTab::for(RequestStage $stage, Request $record): Tab`). The trait
  delegates to it (behavior-preserving); the group's `->tab()` closure calls it with
  `AWAITING_SHIPMENT`.
- Badge semantics for "Fulfillment": ✓ when the request has advanced past
  `AWAITING_SHIPMENT`, ● when it is the current stage. Because stage advancement past
  `AWAITING_SHIPMENT` is already gated on derived completion of **both** channels
  (`goodsChannelComplete()` + `servicesChannelComplete()`), a stage-based ✓ on
  "Fulfillment" correctly means "every applicable channel is complete." This is cleaner
  and more testable than the previous shipments-specific data badge.

### 5. Deep-linking, stage auto-advance, and other integration points

- **`ViewRequest::RELATION_MANAGER_MAP`** — replace the `'shipments' => 6` entry with a
  `'fulfillment' => 6` key (Completion Reports stays 7). Preserve backward-compatible
  deep-linking: an incoming `?activeRelationManager=shipments` (and `acceptanceReports`)
  should still resolve to the Fulfillment group's index, so existing links/redirects keep
  working.
- **`RequestStage::fromRelationManagerKey()`** — map the group key `'fulfillment'` (and
  the legacy `'shipments'`) to `AWAITING_SHIPMENT` for tab-switch auto-advance.
- **`ShipmentsRelationManager::mount()`** redirect to `goodsReceive` and the
  `HasRequestStageTab::mount()` gate redirects are unchanged — they still target valid
  tabs and fire within the child manager context.
- **`RequestInformationFlowWidget`** — update the "Step 7: Inbound Shipments" section
  heading/body to "Fulfillment" and reference both channels; update the widget key lookup
  (`'inbound shipments'`).
- **Empty-state / helper copy** referencing "Inbound Shipments" (e.g.
  `GoodsReceiveRelationManager` empty state, `ShipmentsRelationManager` notifications) →
  "Shipments" for internal consistency.

### 6. Open technical risk to validate first

Filament assigns each `getRelationManagers()` entry an integer index used by
`activeRelationManager` / the `?relation=` param, and `ViewRequest` leans on that
integer↔key mapping for deep-linking and stage auto-advance. `RelationGroup` may key its
children differently. **The first implementation task is a spike** to confirm that:
(a) the group renders one "Fulfillment" tab with correct sub-navigation, (b) the group is
addressable by index for deep-linking, and (c) `updatedActiveRelationManager` still fires
for the group so auto-advance works. If indexing differs, adjust `RELATION_MANAGER_MAP` /
`relationManagerIndexForKey` accordingly.

## Testing

- **Stage-tab badge matrix** (`tests/Feature/Filament/App/Resources/StageTabBadgeMatrixTest.php`):
  the previous test excluded Shipments because it used a bespoke data badge. With the
  Fulfillment group carrying a stage-based badge, **add "Fulfillment" to the tested
  matrix** at `AWAITING_SHIPMENT` and assert ✓/●/null across every stage like the other
  seven tabs.
- **Channel visibility**: a goods-only request exposes only the Shipments sub-item; a
  services-only request exposes only Acceptance Reports; a mixed request exposes both.
- **Services gap fix**: a services-only request can reach and create an acceptance report
  from the Fulfillment tab (regression guard for the dead-wiring).
- **Deep-linking**: `?activeRelationManager=shipments` and `=acceptanceReports` (legacy)
  and `=fulfillment` all open the Fulfillment tab.
- **Existing tests** for shipments and acceptance reports remain green (titles updated
  where they assert "Inbound Shipments").

## Rollout / risk

- Behavior change: acceptance reports become visible on the view page for services/mixed
  requests. This is the intended fix, confirmed with the user.
- Low blast radius: no schema or model changes; the change is tab composition, one shared
  badge helper extraction, and copy.
- The `RelationGroup` indexing spike de-risks the only non-mechanical unknown before the
  bulk of the work.

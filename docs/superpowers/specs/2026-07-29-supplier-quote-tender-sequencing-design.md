# Supplier Quote Tender Sequencing — Design

Date: 2026-07-29
Status: Draft

## Problem

On the Request view page, Supplier Quotes tab (`/{team}/requests/{id}?relation=1`), the
header action buttons misrepresent the tender sequence. Observed on request 15
(REQ-2026-4273, two `received` quotes, none selected): **Compare Quotes** renders enabled
while **Create QE** renders visible-but-greyed-out. Create QE advertises step 3 before the
tender has happened.

The intended business sequence — which the backend already enforces — is:

**Compare Quotes → Apply Selections (winner) → Create QE → QE approved → Announce outcomes**

Three button gates contradict it.

### 1. Compare Quotes disappears instead of guiding

`SupplierQuotesRelationManager.php:1161` gates **visibility** on having ≥2 quotes in
`RECEIVED`/`SELECTED`. A request with one supplier quote never sees the tender entrance at
all — even though `SupplierQuoteComparison::selectSingleSupplier()` (line 110) explicitly
supports a one-supplier tender. Hiding erases the step from the user's mental model;
disabling with a tooltip teaches what unblocks it.

### 2. Create QE is shown too early

`SupplierQuotesRelationManager.php:1201` makes Create QE **visible** whenever any priced
quote exists, then **disables** it (line 1226) until a `SELECTED` quote exists, with the
tooltip "Please apply selected supplier quotes first". This is the inverse of the correct
treatment: the downstream step is the one permanently on screen, while the entrance is the
one that vanishes.

### 3. The comparison modal can announce outcomes with no QE at all

There are **two** announce entry points, and only one respects approval:

| Entry point | Gate | Correct? |
|---|---|---|
| `ViewQuotationEvaluation.php:97` | `QEStatus::APPROVED` (via `canAnnounceSupplierRequestOutcomes()`, line 210) | yes |
| `supplier-quote-comparison.blade.php:64` → `SupplierQuoteComparison::announceOutcomes()` (line 239) | `hasAppliedSelections && ! outcomesAnnounced` | **no — bypasses QE entirely** |

The modal's button lets a user announce a winner to suppliers before any evaluation
exists. This is irreversible: `AnnounceSupplierRequestOutcomes` marks losing quotes
`REJECTED`, emails suppliers their result, and stamps `outcomes_announced_at`, which
permanently locks `applySelections()` (guard at `SupplierQuoteComparison.php:174`).

A blade-only fix is insufficient — public Livewire methods remain reachable via `wire:call`
regardless of template conditionals.

## Goal

Make the UI tell the truth about a sequence the backend already enforces, and close the
announce-before-approval bypass.

Governing rule: **announcing outcomes requires an approved QE. No exceptions.**

## Evidence for removing the obtained carve-out

An earlier draft preserved the modal's announce button for the `obtained` path (which
skips QE by design), reasoning that removing it would strand losing suppliers with no
notification route. Production data shows that scenario does not exist:

| Check | Result |
|---|---|
| Requests with any `obtained` quote | 1 (request 11) |
| Quotes on request 11 | 1 — single-source, no losers to notify |
| Request 11 QE | exists, `approved` — the obtained path went through QE anyway |
| Requests that ever announced outcomes | 1 |

The carve-out protected a hypothetical configuration against a stated principle. Removed.

## Design

Scope: three button gates in `SupplierQuotesRelationManager.php`, plus deletion of the
duplicate announce path in `SupplierQuoteComparison.php` and its blade.

### 1. Compare Quotes — the tender entrance, always present

Replace the `>= 2` **visibility** gate with a price-based **disabled** gate. The action is
always visible on the Supplier Quotes tab.

| State | Condition | Tooltip |
|---|---|---|
| Disabled | No quote in `RECEIVED`/`SELECTED` has an item with `unit_price > 0` | "Enter supplier prices first" |
| Enabled | ≥1 such priced quote, and latest QE is not `APPROVED` | — |
| Disabled | Latest QE is `APPROVED` | "QE approved — winner is final" |

The `RECEIVED`/`SELECTED` status filter matches the modal's own data source
(`SupplierQuoteComparison::quotes()`, line 301, which loads `RECEIVED`, `SELECTED`,
`REJECTED`), so the button is never enabled onto an empty price matrix. "Latest QE" means
`quotationEvaluations()->latest()->first()`, consistent with `HasRequestStageTab.php:50`.

Locking at `APPROVED` rather than at first selection is deliberate: `applySelections()` is
built to be re-run (it clears all `is_selected` flags, re-marks winners, and demotes
deselected quotes back to `RECEIVED`, lines 205–210). The winner must stay correctable
while the QE sits in `NEED_APPROVAL`.

### 2. Create QE — hidden until there is a winner

Visible only when **all** hold:

- a supplier quote with status `SELECTED` exists (the winner has been decided), **and**
- no QE exists for the request yet (existing check, line 1206), **and**
- `! $request->hasObtainedSelectedSupplierQuote()` (existing check, line 1211).

Delete the `->disabled()` and `->tooltip()` block (lines 1226–1243). Visibility now means
availability.

### 3. Announce — one door, behind QE approval

- **Delete** the Announce button from `supplier-quote-comparison.blade.php` lines 64–73.
- **Delete** `SupplierQuoteComparison::announceOutcomes()` (line 239). `ViewQuotationEvaluation`
  calls `AnnounceSupplierRequestOutcomes` directly and does not route through this component.
  Deleting removes the `wire:call` surface entirely rather than guarding it.
- **Keep** the `outcomesAnnounced` computed property (line 272) — the blade still uses it
  for the "selections locked" badge (line 27) and to hide Select Best Prices / Clear / Apply
  (lines 33, 44).
- **`ViewQuotationEvaluation.php:97` unchanged** — announcing stays a manual action gated on
  `QEStatus::APPROVED`, so the approver confirms before supplier emails go out.

### Accepted consequence

The `obtained` path forfeits outcome announcements. No QE means no approval means no
announce. A team that wants losing suppliers notified creates a QE — which is what happened
in the only real `obtained` request. This is stated explicitly rather than left implicit.

## Out of scope

- `Request::hasObtainedSelectedSupplierQuote()` and the `obtained` checkbox (including its
  helper text at `SupplierQuotesRelationManager.php:282`) are unchanged.
- The stage gate in `HasRequestStageTab::mount()` (lines 47–66) is unchanged. It already
  hard-blocks every stage past Supplier Quotes unless the QE is `APPROVED` or the request
  has an obtained+selected quote.
- `AnnounceSupplierRequestOutcomes` itself is unchanged.
- `QEStatus` has only `NEED_APPROVAL` and `APPROVED` — a QE sent back for rework has no
  state to sit in. With Compare Quotes locking only at `APPROVED` this is tolerable, but it
  is a real gap deserving its own change.

## Testing

New `SupplierQuoteHeaderActionsTest` (Filament relation-manager feature test):

- Compare Quotes visible and **disabled** when no quote has a priced item.
- Compare Quotes **enabled** with one priced quote (single-supplier tender reachable).
- Compare Quotes **disabled** when the latest QE is `APPROVED`.
- Create QE **hidden** when quotes are priced but none is `SELECTED` — the reported bug.
- Create QE **visible** after `applySelections()` produces a `SELECTED` quote.
- Create QE **hidden** when a QE already exists.
- Create QE **hidden** on the obtained path (`hasObtainedSelectedSupplierQuote()`).

Modal announce removal:

- `SupplierQuoteComparison` no longer exposes `announceOutcomes` (assert the Livewire call
  fails / the method is absent).

Existing test migration:

- `tests/Feature/SupplierPortal/SupplierRequestOutcomeTest.php:277` ("locks applySelections
  for the round after announcement") currently calls `announceOutcomes` on the Livewire
  component. Rewrite it to invoke `AnnounceSupplierRequestOutcomes` directly, matching the
  sibling tests in that file (lines 152, 203, 229, 266). Announce behavior coverage is
  already exercised against the action, so it survives intact.

## Verification

Per project quality gates, on the changed files:

```bash
php vendor/bin/rector process <changed files>
php vendor/bin/pint --dirty
php artisan test --compact --filter=SupplierQuoteHeaderActions
php artisan test --compact tests/Feature/SupplierPortal/SupplierRequestOutcomeTest.php
```

Manual check on request 15 (`/1/requests/15?relation=1`): Compare Quotes enabled, Create QE
absent. After applying a selection in the modal, Create QE appears.

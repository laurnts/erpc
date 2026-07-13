# CI Quality Gate Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the CI "Code Quality Checks" job green by applying the 127-file Rector backlog safely, and make the one stale catalog image conversion consistent.

**Architecture:** This is a mechanical-refactor plan, not a feature plan. Rector rewrites are applied wholesale, then verified by the exact four gates CI runs (`test:lint`, `test:refactor`, `test:type-coverage`, `test:types`) plus the full Pest suite. Risky rule output is eyeballed by rule category before running the expensive gates. Any rule that breaks tests gets added to `withSkip()` in `rector.php` rather than hand-patching its output.

**Tech Stack:** Rector 2.3.4 (+ rector-laravel), Pint, PHPStan level 7 (larastan, 1975-line baseline with `reportUnmatchedIgnoredErrors: true`), Pest 4, Laravel 12, PHP 8.4 via Docker wrapper (`php` on PATH runs inside the `php` container).

## Global Constraints

- All commands run through the Docker PHP wrapper: use `php vendor/bin/<tool>`, never bare `phpstan`/`pest`/`rector` (bare names fail with "Permission denied" / not found).
- Repo convention: work directly on `main`; push only after every gate passes locally.
- Full Pest suite takes ~33 minutes locally (1877 tests). Run it in the background and wait; do not skip it.
- Do not remove or weaken any existing test (project rule).
- Working tree must be clean before starting (`git status --short` prints nothing). If another session has uncommitted edits, STOP and ask the user — foreign hunks are a stop sign.
- Commit message trailer (project convention): `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`

## Background (why this plan exists)

`composer test:refactor` (= `rector --dry-run`) currently flags **127 files**, and CI runs it as a required step in `.github/workflows/tests.yml` → the "Code Quality Checks" job fails on every push. The backlog is dominated by mechanical rules:

| Count | Rule | Risk |
|---|---|---|
| 30 | FlipTypeControlToUseExclusiveTypeRector | low (comparison style) |
| 15 | RemoveUnusedVariableAssignRector | low |
| 15 | AddClosureVoidReturnTypeWhereNoReturnRector | low |
| 15 | AddArrowFunctionReturnTypeRector | low |
| 12 | **RemoveUnusedPublicMethodParameterRector** | **medium — can break dynamic/closure callers** |
| 12 | AddTypeToConstRector | low |
| 11 | SortCallLikeNamedArgsRector | low (cosmetic) |
| 11 | ChangeOrIfContinueToMultiContinueRector | low |
| 10 | RemoveUnusedVariableInCatchRector | low |
| 8 | NullToStrictStringFuncCallArgRector | low (`(string)` casts) |
| 8 | ClosureToArrowFunctionRector | low |
| 7 | **ScopeNamedClassMethodToScopeAttributedClassMethodRector** | **medium — renames `scopeX()` to `#[Scope] x()`; orphans PHPStan baseline entries** |
| 7 | RepeatedOrEqualToInArrayRector | low |
| 7 | DisallowedEmptyRuleFixerRector | low |

Already fixed, NOT part of this plan (verify only, don't redo):
- Duplicate portal-invite 500 → fixed 2026-07-08 by migration `2026_07_08_103157_widen_portal_invitations_unique_index_with_portal` + covered by `tests/Feature/Portal/InvitePortalUserActionTest.php` ("allows inviting the same email to both portals of a dual-role company").
- Stale `PortalTimelineLeakTest` + missing `readonly` on `PaymentTermsDescription` → fixed and pushed in `357fe52f`.

---

### Task 1: Apply Rector and review the diff by rule category

**Files:**
- Modify: ~127 files under `app/`, `app-modules/`, `config/`, `database/` (Rector-generated)
- Possibly modify: `rector.php` (only if a rule must be skipped — see Step 4)

**Interfaces:**
- Consumes: current `rector.php` config (do not change sets/paths).
- Produces: a working tree where `php vendor/bin/rector --dry-run` reports zero changes; later tasks rely on this exact state.

- [ ] **Step 1: Verify clean starting state**

Run: `git -C /Users/laurnts/Sites/erpc status --short`
Expected: no output. If there IS output, stop and ask the user before proceeding.

- [ ] **Step 2: Apply Rector**

Run: `php vendor/bin/rector 2>&1 | tail -5`
Expected: `[OK] 127 files have been changed by Rector` (count may drift ±a few if code moved since 2026-07-13).

- [ ] **Step 3: Apply Pint on top (Rector output is not Pint-styled)**

Run: `php vendor/bin/pint --dirty 2>&1 | tail -3`
Expected: `PASS ... N files`.

- [ ] **Step 4: Eyeball the two medium-risk rule outputs**

Review every removed public-method parameter — this rule breaks callers that pass the argument dynamically (Filament closures, `app()->call()`, event listeners):

```bash
git -C /Users/laurnts/Sites/erpc diff -U3 | grep -B5 -A5 'public function' | grep -A5 -B5 '^-.*public function.*,' | head -100
```

And list every scope conversion so you know which PHPStan baseline entries will orphan:

```bash
git -C /Users/laurnts/Sites/erpc diff | grep -E '^\-.*function scope' 
```

Expected: 7 scope renames (e.g. `scopeForBuyerCompany` → `forBuyerCompany` in `app/Models/Shipment.php`), ~12 parameter removals.

For each removed parameter, grep for call sites passing that argument:

```bash
grep -rn '<methodName>(' /Users/laurnts/Sites/erpc/app /Users/laurnts/Sites/erpc/app-modules /Users/laurnts/Sites/erpc/resources /Users/laurnts/Sites/erpc/tests --include='*.php' --include='*.blade.php'
```

If a call site still passes the removed argument AND the method is invoked dynamically (so PHP won't error but behavior silently changes), revert that file and add a skip entry in `rector.php` following the existing pattern:

```php
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
// inside ->withSkip([...])
RemoveUnusedPublicMethodParameterRector::class => [
    // <reason: parameter is consumed via dynamic invocation>
    __DIR__.'/app/Path/To/File.php',
],
```

Then re-run Steps 2–3 (Rector must exit clean with the skip in place).

- [ ] **Step 5: Confirm Rector gate is clean**

Run: `php vendor/bin/rector --dry-run 2>&1 | tail -3`
Expected: `[OK] Rector is done!` with zero files (this is what CI's `composer test:refactor` needs).

### Task 2: Re-align the PHPStan baseline and remaining static gates

**Files:**
- Modify: `phpstan-baseline.neon` (regenerated — expect entry count to shrink or shift)
- Test: none (static analysis gates)

**Interfaces:**
- Consumes: Task 1's working tree.
- Produces: all four CI Code Quality steps passing locally.

- [ ] **Step 1: Run PHPStan**

Run: `php vendor/bin/phpstan analyse --no-progress 2>&1 | tail -10`

Two acceptable outcomes:
- `[OK] No errors` → skip Step 2, go to Step 3.
- Errors of the form `Ignored error pattern ... was not matched in reported errors` (orphaned baseline entries from the scope renames) and/or new genuine findings → Step 2.

- [ ] **Step 2 (conditional): Regenerate the baseline**

Only if Step 1 reported unmatched-ignore errors AND no *new* genuine errors:

Run: `php vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon --no-progress 2>&1 | tail -3`
Expected: `[OK] Baseline generated with N errors` where **N ≤ the current count** (grep count before/after: `grep -c 'message:' phpstan-baseline.neon`). If N grew, Rector introduced new type errors — inspect them with `git stash` on the baseline (`git checkout phpstan-baseline.neon && php vendor/bin/phpstan analyse --no-progress`) and fix the offending files by hand before regenerating; do NOT bury new errors in the baseline (the whole point of commit `bc15b047` was that new type errors gate CI).

Then re-run Step 1. Expected: `[OK] No errors`.

- [ ] **Step 3: Run the two remaining CI quality gates**

Run: `php vendor/bin/pint --test 2>&1 | tail -3`
Expected: `PASS` (CI runs `composer test:lint` = `pint --test`; we ran fixing pint in Task 1, so this must already pass).

Run: `php vendor/bin/pest --type-coverage --min=99.9 2>&1 | tail -5`
Expected: total ≥ 99.9%. If below: Rector removed a param that carried the only type declaration somewhere — find untyped lines in the output, add explicit types, re-run.

### Task 3: Full test suite

**Files:**
- Modify: none expected; only if failures surface.

**Interfaces:**
- Consumes: Tasks 1–2 tree.
- Produces: green suite proving the mechanical rewrite changed no behavior.

- [ ] **Step 1: Run the full suite in the background (~33 min)**

Run: `php artisan test --compact 2>&1 | tail -15` (background; wait for completion)
Expected: `Tests: 10 skipped, 1877 passed` (2 more than the 2026-07-13 baseline if new tests landed since; **zero failed** is the requirement).

- [ ] **Step 2 (conditional): Handle failures**

For each failing test, diff the file(s) it touches (`git diff -- <file>`), identify the responsible Rector rule from the changed hunk, and prefer reverting the file + adding a `withSkip()` entry (Task 1 Step 4 pattern) over hand-editing Rector's output. Then re-run Task 1 Step 5, Task 2 Steps 1–3, and the failing test file only:

```bash
php artisan test --compact tests/Path/To/FailingTest.php
```

Re-run the full suite once more only if any reverted file is shared across domains (models, services); for leaf files (a single resource page) the targeted re-run suffices.

### Task 4: Commit and push; verify CI

**Files:**
- Commit: everything from Tasks 1–3.

- [ ] **Step 1: Stage and commit explicitly**

```bash
git -C /Users/laurnts/Sites/erpc add -A
git -C /Users/laurnts/Sites/erpc status --short   # review: ONLY rector/pint/baseline changes, no foreign files
git -C /Users/laurnts/Sites/erpc commit -m "chore: apply rector backlog (scope attributes, strict casts, dead code)

Mechanical rector run to green the CI Code Quality gate; phpstan baseline
regenerated for renamed scope methods. No behavior changes — full suite green.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

(`add -A` is acceptable here only because Step 1 of Task 1 verified the tree was clean before Rector ran; the status review is the guard.)

- [ ] **Step 2: Push**

Run: `git -C /Users/laurnts/Sites/erpc push origin main`
Expected: push accepted.

- [ ] **Step 3: Verify CI goes green**

`gh` is not installed and the repo is private to anonymous API calls. Ask the user to confirm the Actions run, or watch it in the browser at `https://github.com/laurnts/erpc/actions`. Expected: "Code Quality Checks" job passes all four steps; all 7 test shards pass.

### Task 5: Regenerate the stale catalog image conversion (ops, no commit)

**Files:** none (regenerates files under `storage/` / media disk only).

- [ ] **Step 1: Regenerate `medium` conversions for product images**

Run: `php artisan media-library:regenerate --only-missing=0 2>&1 | tail -3` — check first with `php artisan media-library:regenerate --help`; if the installed version supports it, prefer the narrower `php artisan media-library:regenerate --only=medium`.
Expected: exit 0, ~10 media items processed.

Context: the `medium` conversion definition changed on 2026-07-13 (`Fit::Contain` → `Fit::Fill` + white background, `app/Models/Article.php:159`). Nine images were uploaded after the change; only media id 5 (uploaded 2026-07-05) still has the old-style file.

- [ ] **Step 2: Verify visually**

Load `http://erpc.test/` and confirm the July-5 product's card image has a white (not transparent/letterboxed) background matching the others. Expected: all cards visually consistent.

---

## Self-Review Notes

- **Coverage:** the two outstanding audit items (Rector CI gate, stale conversion) each have a task; the two already-fixed items are documented as out of scope so an executor doesn't redo them.
- **Deliberately excluded:** no new feature work, no test removals, no phpstan-level bump.
- **Known unknowns:** exact Rector fallout is unknowable until applied; contingencies are concrete (skip-list pattern with real syntax from the existing `rector.php`, baseline before/after count check) rather than "handle failures appropriately".

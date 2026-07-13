# CLAUDE.md / AI-Config Alignment — Design

**Date:** 2026-07-14
**Status:** Approved

## Problem

The AI agent configuration has drifted from the actual project state. An audit
(2026-07-14) found:

1. **Missing vendor guidelines.** `boost.json`'s `"guidelines"` allowlist was
   empty, so Laravel Boost silently dropped every third-party guideline package
   during `boost:update`. Three eligible packages ship
   `resources/boost/guidelines/core.blade.php`: `filament/filament` (~4.5KB,
   core Filament v5 conventions), `prism-php/prism` (~6.4KB, LLM package behind
   AI summaries), and `filament/blueprint` (~0.3KB, planning-mode pointer;
   already re-enabled). `laravel/fortify` also ships one but is not a direct
   composer requirement, so Boost cannot pick it up.
2. **Phantom commands.** `.claude/rules/testing.md` documents `pnpm test`,
   `pnpm test:arch`, `pnpm test:coverage`, `pnpm test:type-coverage` — none
   exist (`package.json` has only `build`/`dev`). Real commands are the
   `composer` scripts. `pnpm-lock.yaml` is a stale upstream leftover (untouched
   since 2026-01-12); npm is canonical (`composer dev` invokes `npx`/`npm`).
3. **Stale spec list.** CLAUDE.md lists 9 of 21 specs in `openspec/specs/`,
   missing the entire ERP domain (erp-trading-core, erp-quoting, erp-orders,
   erp-shipments, erp-finance, erp-activity-logging, buyer-portal,
   supplier-portal, buyer-quotes, public-catalog, document-storage,
   request-activity-timeline).
4. **Incomplete module rules.** `.claude/rules/module-isolation.md` covers only
   `SystemAdmin`; `Documentation` and `OnboardSeed` modules exist with no
   stated boundaries.
5. **Missing rules-file listing.** CLAUDE.md's Quick Start omits
   `.claude/rules/decision-guide.md`.
6. **Identity drift.** Config titled "Relaticle CRM" with "CRM entities"
   wording; the project is an ERP (requests, quotes, orders, fulfillment,
   finance, buyer/supplier portals).
7. **Command-form ambiguity.** Boost's pint rule says `vendor/bin/pint --dirty`
   while the Quality Gates section mandates `php vendor/bin/pint` (Docker
   wrapper). Both work (shebang resolves to the Docker php shim), but one form
   should be stated canonical.

## Decisions (user-confirmed)

- Enable **all three** vendor guidelines in `boost.json`.
- Spec list: **full regenerated list** of all 21 specs with one-line
  descriptions (manual upkeep accepted).
- Terminology: **full rewrite** — CLAUDE.md and tracked `.claude/rules/` files
  move from CRM to ERP identity.
- Approach: **direct edits** (Approach A), not a `.ai/guidelines/` restructure.
  Rationale: the drift is content rot, not structure; Boost-native placement
  can be adopted later without redoing content.

## Ground truth for module rules (from code + `tests/ArchTest.php`)

- `App` must not use `Relaticle\SystemAdmin` (exceptions:
  `AppServiceProvider`, `InstallCommand`, `CreateSystemAdminCommand`).
- `Relaticle\SystemAdmin` must not use `App` except `App\Models`, `App\Enums`.
- `Relaticle\OnboardSeed` freely uses `App\Models` / `App\Enums` (Company,
  People, Team, User, CreationSource…). Not arch-tested; document as allowed.
- `Relaticle\Documentation` imports nothing from `App`; self-contained.
- `App` touches modules only from providers, listeners, and console commands.

## Changes

### 1. `boost.json` + regenerate (local-only file)
`"guidelines": ["filament/blueprint", "filament/filament", "prism-php/prism"]`,
then `php artisan boost:update`. Verify: three `=== <package> rules ===`
sections inside `<laravel-boost-guidelines>`; zero diff outside the tags.

### 2. `CLAUDE.md` middle section (local-only file)
- Retitle: "ERPC — B2B Trading ERP" (keep "forked from Relaticle CRM" note for
  provenance).
- Reword "CRM entities" → "team-scoped entities" with ERP examples.
- Replace the 9-item spec list with all 21 (one-liners, grouped: ERP core,
  portals, platform, CRM legacy).
- Add `decision-guide.md` to the Quick Start rules list.
- State canonical tool form: `php vendor/bin/<tool>` (Docker wrapper),
  overriding the Boost pint rule's bare `vendor/bin/pint`.

### 3. `.claude/rules/testing.md` (tracked)
Replace `pnpm` commands with `composer test`, `composer test:arch`,
`composer test:coverage`, `composer test:type-coverage`.

### 4. `.claude/rules/module-isolation.md` (tracked)
Document all three modules per ground truth above, including the arch-test
exception lists.

### 5. `.claude/rules/multi-tenancy.md`, `.claude/rules/decision-guide.md` (tracked)
Terminology sweep only (CRM → ERP/team-scoped). No rule-content changes.

### 6. Verification & commit
- Diff CLAUDE.md before/after regen; assert only managed-block changes from
  Boost, only middle-section changes from hand edits.
- `composer test:arch` must pass (module rules mirror the tests).
- Commit **only** tracked `.claude/rules/` changes (+ this spec). `boost.json`
  and `CLAUDE.md` stay uncommitted per Laravel's gitignore recommendation.

## Error handling

- If `boost:update` produces unexpected diffs outside the managed block,
  restore from the scratchpad backup (`CLAUDE.md.before`) and stop.
- If arch tests fail after rule edits, the *rules doc* is wrong, not the code —
  fix the doc to match `tests/ArchTest.php`.

## Out of scope

- `.ai/guidelines/` restructure (Approach B).
- Removing stale `pnpm-lock.yaml` (flag to user separately; deletion is a
  repo-visible change beyond doc alignment).
- Boost upgrade / `boost.json` schema migration (`guidelines` array → boolean +
  `packages` key on Boost main).

## Success criteria

- CLAUDE.md contains Filament, Prism, and Blueprint guideline sections.
- Every command an agent can copy from CLAUDE.md or `.claude/rules/` executes
  successfully.
- Spec list matches `openspec list --specs` (21 entries).
- Module rules match `tests/ArchTest.php` and actual imports.
- No CRM-era terminology in tracked rules or the hand-written CLAUDE.md
  section.

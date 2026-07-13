# CLAUDE.md / AI-Config Alignment — Design

**Date:** 2026-07-14
**Status:** Approved

## Problem

The AI agent configuration has drifted from the actual project state. An audit
(2026-07-14) found:

1. **Missing vendor guidelines.** `boost.json`'s `"guidelines"` allowlist was
   empty at audit time, so Laravel Boost silently dropped every third-party
   guideline package during `boost:update`. Three eligible packages ship
   `resources/boost/guidelines/core.blade.php`: `filament/filament` (~4.5KB,
   core Filament v5 conventions), `prism-php/prism` (~6.4KB, LLM package behind
   AI summaries), and `filament/blueprint` (~0.3KB, planning-mode pointer;
   re-enabled and regenerated during the audit, so `boost.json` now reads
   `["filament/blueprint"]`). `laravel/fortify` also ships one but is not a
   direct composer requirement, so Boost cannot pick it up.
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
  People, Team, User, CreationSource, CustomFields\PeopleField — exhaustive as
  of audit). Not arch-tested; document as allowed.
- `Relaticle\Documentation` imports nothing from `App`; self-contained.
- `App` touches modules only from providers, listeners, and console commands.

## Changes

### 1. `boost.json` + regenerate (local-only file)
Step 0: back up `CLAUDE.md` **and** `AGENTS.md` to the session scratchpad.
`boost:update` regenerates both — `boost.json` configures three agents
(`claude_code`, `codex`, `opencode`) and the latter two target `AGENTS.md`.
Both files are gitignored; `AGENTS.md` has no hand-written section (OpenSpec
block + managed block only), so it needs no content mirroring — just verify
its changes stay inside the managed block.

Then set
`"guidelines": ["filament/blueprint", "filament/filament", "prism-php/prism"]`
and run `php artisan boost:update`. Verify: three `=== <package> rules ===`
sections inside `<laravel-boost-guidelines>` in both files; zero diff outside
the tags in either file; `git status --porcelain` shows no tracked file
changed by the regeneration.

### 2. `CLAUDE.md` middle section (local-only file)
- Retitle: "ERPC — B2B Trading ERP" and **add** a one-line "forked from
  Relaticle CRM" provenance note (no such note exists yet).
- Reword "CRM entities" → "team-scoped entities" with ERP examples.
- Replace the 9-item spec list with all 21 (one-liners, grouped: ERP core,
  portals, platform, CRM legacy).
- Add `decision-guide.md` to the Quick Start rules list.
- State canonical tool form: `php vendor/bin/<tool>` (Docker wrapper). This is
  a standing precedence override — the managed block will keep saying
  `vendor/bin/pint --dirty` on every regen — so the sentence must name what it
  overrides explicitly (e.g. "this overrides the Boost pint rule's
  `vendor/bin/pint` form below").

### 3. `.claude/rules/testing.md` (tracked)
Replace `pnpm` commands with `composer test`, `composer test:arch`,
`composer test:coverage`, `composer test:type-coverage`.

### 4. `.claude/rules/module-isolation.md` (tracked)
Document all three modules per ground truth above, including the arch-test
exception lists.

### 5. `.claude/rules/multi-tenancy.md`, `.claude/rules/decision-guide.md` (tracked)
Terminology sweep only (CRM → ERP/team-scoped). No rule-content changes.

### 6. Verification & commit

The original defect was a documented-but-never-executed command, so the
verification must *run* everything the docs promise, not just diff text:

```bash
# Criterion 1 — guideline sections present (expect 3, in CLAUDE.md and AGENTS.md)
grep -c '^=== \(filament/filament\|prism-php/prism\|filament/blueprint\) rules ===' CLAUDE.md

# Criterion 2 — every command documented in CLAUDE.md / .claude/rules runs
composer test:arch
composer test:type-coverage
composer test:coverage   # proves a coverage driver exists in the Docker image;
                         # if it errors, do NOT document it — adjust testing.md
openspec list --specs
php vendor/bin/pint --dirty  # canonical wrapper form works (no-op on clean tree)

# Criterion 3 — spec list complete
ls openspec/specs | wc -l    # expect 21; names must match the new CLAUDE.md list

# Criterion 5 — terminology sweep
grep -rni 'crm' .claude/rules/   # expect zero hits
grep -ni 'crm' CLAUDE.md         # expect only: provenance note, crm-core spec
                                 # name, occurrences inside the managed block
```

- Diff CLAUDE.md and AGENTS.md before/after regen; assert only managed-block
  changes from Boost, only middle-section changes from hand edits;
  `git status --porcelain` clean of unexpected tracked changes.
- `composer test:arch` must pass (module rules mirror the tests).
- Commit tracked changes only. **Implementation correction:** `.claude/` is
  fully gitignored (`.gitignore:28`) — the audit and review both wrongly
  assumed `.claude/rules/` was tracked. All rules edits are local-only, like
  `boost.json`, `CLAUDE.md`, and `AGENTS.md`.

### 7. `composer.json` scripts (tracked) — added during implementation

Verification exposed that every composer script invoking a bare tool
(`pest`/`pint`/`rector`/`phpstan`) exits 127 ("Permission denied") in the
Docker dev environment: the volume mount reports `vendor/bin/*` as 0666
inside the container even when the host has exec bits. Fixed by prefixing
those scripts with `@php vendor/bin/` (standard Laravel idiom, works
everywhere). This is why the repo convention `php vendor/bin/<tool>` existed.
Verified after fix: `composer test:arch` 46 passed, type coverage 100%.
Also: bare `openspec validate` requires an interactive terminal; CLAUDE.md
now documents `openspec validate --all`.

## Error handling

- If `boost:update` produces unexpected diffs outside the managed block,
  restore from the scratchpad backups (`CLAUDE.md.before`, `AGENTS.md.before`
  — created in Change 1 step 0) and stop.
- If `composer test:coverage` fails for lack of a coverage driver, document
  only the commands that actually run and note the limitation in testing.md.
- If arch tests fail after rule edits, the *rules doc* is wrong, not the code —
  fix the doc to match `tests/ArchTest.php`.

## Out of scope

- `.ai/guidelines/` restructure (Approach B).
- Removing stale `pnpm-lock.yaml` (flag to user separately; deletion is a
  repo-visible change beyond doc alignment).
- Boost upgrade / `boost.json` schema migration (`guidelines` array → boolean +
  `packages` key on Boost main).

## Success criteria

- CLAUDE.md and AGENTS.md contain Filament, Prism, and Blueprint guideline
  sections.
- Every command an agent can copy from CLAUDE.md or `.claude/rules/` executes
  successfully (verified by running each one, per Change 6).
- Spec list matches `openspec list --specs` (21 entries).
- Module rules match `tests/ArchTest.php` and actual imports.
- No CRM-era terminology in tracked rules or the hand-written CLAUDE.md
  section, **except** the provenance note and legacy spec names (`crm-core`);
  the Boost-managed block is exempt (regenerated content).

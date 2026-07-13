# AI-Config Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make CLAUDE.md, AGENTS.md, boost.json, and `.claude/rules/*` factually match the project (ERP identity, real commands, all 21 specs, all 3 modules, all eligible vendor Boost guidelines), with every documented command executed to prove it works.

**Architecture:** Direct edits per the approved spec (`docs/superpowers/specs/2026-07-14-claude-md-alignment-design.md`, Approach A). Boost-managed blocks are regenerated via `php artisan boost:update`; hand-written sections are edited in place; tracked rule files are committed, gitignored files are not.

**Tech Stack:** Laravel Boost v1.8.10, OpenSpec CLI, Pest 4 (arch tests), Docker php wrapper.

## Global Constraints

- All PHP tooling runs through the Docker wrapper: `php artisan …`, `php vendor/bin/<tool>` — never bare `rector`/`pint`/`phpstan`.
- Never edit inside `<laravel-boost-guidelines>…</laravel-boost-guidelines>` or the OpenSpec managed block by hand — those regenerate.
- Commit ONLY tracked files: `.claude/rules/*` and the two docs/superpowers files. `CLAUDE.md`, `AGENTS.md`, `boost.json` are gitignored and stay uncommitted.
- Commit messages end with the Claude trailer (Co-Authored-By + Claude-Session) per session convention.
- Scratchpad for backups: `/private/tmp/claude-501/-Users-laurnts-Sites-erpc/92f949b1-6e26-4a24-8834-c7cb4a4df11b/scratchpad`
- Working directory: `/Users/laurnts/Sites/erpc`.

---

### Task 1: Enable vendor guidelines in boost.json and regenerate

**Files:**
- Modify: `boost.json` (gitignored)
- Regenerated: `CLAUDE.md`, `AGENTS.md` (both gitignored)

**Interfaces:**
- Produces: `CLAUDE.md`/`AGENTS.md` managed blocks containing `=== filament/blueprint rules ===`, `=== filament/filament rules ===`, `=== prism-php/prism rules ===`. Task 6 greps for these.

- [ ] **Step 1: Back up both regeneration targets**

```bash
S=/private/tmp/claude-501/-Users-laurnts-Sites-erpc/92f949b1-6e26-4a24-8834-c7cb4a4df11b/scratchpad
cp CLAUDE.md "$S/CLAUDE.md.task1-before"
cp AGENTS.md "$S/AGENTS.md.task1-before"
```

- [ ] **Step 2: Set the allowlist**

Edit `boost.json` so the `guidelines` key reads exactly:

```json
    "guidelines": [
        "filament/blueprint",
        "filament/filament",
        "prism-php/prism"
    ]
```

(Leave `agents` and `editors` keys untouched.)

- [ ] **Step 3: Regenerate**

Run: `php artisan boost:update --no-interaction`
Expected: guideline table in output includes `filament/blueprint`, `filament/filament` and `prism-php/prism`; ends `INFO Boost guidelines updated successfully.`

- [ ] **Step 4: Verify sections and containment**

```bash
grep -c '^=== \(filament/filament\|prism-php/prism\|filament/blueprint\) rules ===' CLAUDE.md   # expect 3
grep -c '^=== \(filament/filament\|prism-php/prism\|filament/blueprint\) rules ===' AGENTS.md   # expect 3
S=/private/tmp/claude-501/-Users-laurnts-Sites-erpc/92f949b1-6e26-4a24-8834-c7cb4a4df11b/scratchpad
# All diff hunks must fall between the <laravel-boost-guidelines> tags:
diff "$S/CLAUDE.md.task1-before" CLAUDE.md | head -40
diff "$S/AGENTS.md.task1-before" AGENTS.md | head -40
git status --porcelain   # expect: empty (no tracked file changed)
```

If any diff hunk falls outside the managed block, restore both backups and STOP — report the anomaly.

No commit (all three files gitignored).

---

### Task 2: Fix .claude/rules/testing.md (phantom pnpm commands)

**Files:**
- Modify: `.claude/rules/testing.md` (tracked)

**Interfaces:**
- Produces: documented commands `composer test`, `composer test:arch`, `composer test:coverage`, `composer test:type-coverage` — Task 6 executes each.

- [ ] **Step 1: Probe whether a coverage driver exists (decides file content)**

Run: `php -m | grep -iE 'xdebug|pcov'`
- If a driver is listed → include the `test:coverage` line as-is below.
- If NOT listed, also run `composer test:coverage 2>&1 | tail -5`; if it errors with "No code coverage driver available", replace that line in the file below with:
  `composer test:coverage  # requires Xdebug/PCOV in the Docker php image`
  and note the actual behavior observed.

- [ ] **Step 2: Rewrite the file**

Write `.claude/rules/testing.md` with exactly this content (adjust the coverage line per Step 1):

````markdown
# Testing Rules

## Requirements
- **Coverage:** Minimum 80%
- **Type Coverage:** 99.9%
- **Framework:** Pest 4 with Laravel/Livewire plugins
- **Architecture Tests:** Enforce strict types, final classes

## Commands

All commands run through the Docker php wrapper (`composer` resolves it automatically):

```bash
composer test                 # Full test suite (lint check, refactor check, PHPStan, type coverage, Pest)
composer test:arch            # Architecture tests only
composer test:coverage        # Code coverage
composer test:type-coverage   # Type coverage
php artisan test --compact --filter=testName   # Single test during development
```
````

- [ ] **Step 3: Verify every documented command executes**

```bash
composer test:arch            # expect: PASS
composer test:type-coverage   # expect: PASS (type coverage 100%)
```
(`composer test` full suite runs in Task 6 once; `test:coverage` behavior verified in Step 1.)

- [ ] **Step 4: Commit**

```bash
git add .claude/rules/testing.md
git commit -m "docs: replace phantom pnpm test commands with real composer scripts

pnpm test/test:arch/test:coverage/test:type-coverage never existed in
package.json (upstream Relaticle leftover); the real commands are composer
scripts run through the Docker php wrapper."
```

---

### Task 3: Rewrite .claude/rules/module-isolation.md for all three modules

**Files:**
- Modify: `.claude/rules/module-isolation.md` (tracked)
- Reference (read-only): `tests/ArchTest.php:130-147`

**Interfaces:**
- Consumes: arch-test ground truth (App↔SystemAdmin boundaries + exceptions).
- Produces: rules text that Task 6's `composer test:arch` run must not contradict.

- [ ] **Step 1: Rewrite the file**

Write `.claude/rules/module-isolation.md` with exactly this content:

```markdown
# Module Isolation Rules

Modules live in `app-modules/{Module}/` under the `Relaticle\{Module}` namespace.

## SystemAdmin (`Relaticle\SystemAdmin`) — strict, arch-tested (tests/ArchTest.php)
- `App\` MUST NOT depend on `Relaticle\SystemAdmin`.
  Allowed exceptions (bootstrap only): `App\Providers\AppServiceProvider`,
  `App\Console\Commands\InstallCommand`, `App\Console\Commands\CreateSystemAdminCommand`.
- `Relaticle\SystemAdmin` MUST NOT depend on `App\` except `App\Models` and `App\Enums`.

## OnboardSeed (`Relaticle\OnboardSeed`)
- Seeds demo/onboarding data for new teams.
- MAY depend on `App\Models` and `App\Enums` (not arch-tested).

## Documentation (`Relaticle\Documentation`)
- Self-contained; imports nothing from `App\`.

## General
- `App\` reaches into modules only from providers, listeners, and console commands.
- New modules: add an arch test in `tests/ArchTest.php` stating their boundary.
```

- [ ] **Step 2: Verify rules match the arch tests**

Run: `composer test:arch`
Expected: PASS (the doc mirrors `tests/ArchTest.php`; if it fails, the doc is wrong — fix the doc, never the code).

- [ ] **Step 3: Commit**

```bash
git add .claude/rules/module-isolation.md
git commit -m "docs: document all three app-modules boundaries, not just SystemAdmin

Documentation and OnboardSeed existed with no stated rules; boundaries
sourced from tests/ArchTest.php and actual imports."
```

---

### Task 4: Terminology sweep in multi-tenancy.md and decision-guide.md

**Files:**
- Modify: `.claude/rules/multi-tenancy.md` (tracked)
- Modify: `.claude/rules/decision-guide.md` (tracked)

**Interfaces:**
- Produces: zero `crm` matches in `.claude/rules/` — Task 6 greps.

- [ ] **Step 1: Edit multi-tenancy.md**

Three replacements (rule content unchanged):
- `All CRM entities MUST:` → `All team-scoped ERP entities (requests, quotes, orders, articles, companies, people, …) MUST:`
- `CRM entities SHOULD use` → `Team-scoped entities SHOULD use`
- Title/intro mentions of "CRM" (if any) → "ERP".

- [ ] **Step 2: Edit decision-guide.md**

In the Model Traits table: `| Team-based multi-tenancy | All CRM entities |` → `| Team-based multi-tenancy | All team-scoped entities |`. Sweep any other `CRM` occurrence in the file the same way (`grep -n -i crm .claude/rules/decision-guide.md` first).

- [ ] **Step 3: Verify**

Run: `grep -rni 'crm' .claude/rules/`
Expected: no output, exit code 1.

- [ ] **Step 4: Commit**

```bash
git add .claude/rules/multi-tenancy.md .claude/rules/decision-guide.md
git commit -m "docs: CRM -> ERP/team-scoped terminology in rules files"
```

---

### Task 5: Rewrite CLAUDE.md hand-written middle section

**Files:**
- Modify: `CLAUDE.md` (gitignored) — ONLY the section between the OpenSpec managed block and `<laravel-boost-guidelines>`.

**Interfaces:**
- Consumes: nothing from other tasks (independent of Task 1's managed-block changes).
- Produces: retitled ERP section, 21-spec list, canonical tool form — Task 6 greps.

- [ ] **Step 1: Back up**

```bash
S=/private/tmp/claude-501/-Users-laurnts-Sites-erpc/92f949b1-6e26-4a24-8834-c7cb4a4df11b/scratchpad
cp CLAUDE.md "$S/CLAUDE.md.task5-before"
```

- [ ] **Step 2: Apply edits to the hand-written section**

(a) Title + provenance. Replace:

```markdown
# Relaticle CRM - Claude Code Configuration
```

with:

```markdown
# ERPC — B2B Trading ERP - Claude Code Configuration

ERP for B2B trading: buyer requests → supplier quotes → orders → fulfillment
(shipments & acceptance reports) → invoicing/payments. Forked from Relaticle CRM;
the CRM base (companies, people, custom fields, teams) remains underneath.
```

(b) Quick Start rules list — replace the 5-item list with:

```markdown
Rules in `.claude/rules/` (auto-loaded):
- `core.md` - Strict types, final classes, readonly, comparisons, database conventions
- `naming.md` - File and class naming patterns
- `multi-tenancy.md` - Team-based scoping, observers
- `module-isolation.md` - App/module boundaries (SystemAdmin, Documentation, OnboardSeed)
- `testing.md` - Coverage requirements
- `decision-guide.md` - Pattern choice quick reference (Service vs Action, traits, cascade rules)
```

(c) OpenSpec specs list — replace the entire "Active specifications in `openspec/specs/`:" list with:

```markdown
Active specifications in `openspec/specs/` (run `openspec list --specs` for the authoritative list):

**ERP core**
- `erp-trading-core` - Requests (RFQs), articles, suppliers, line items; item-level mixed goods/services fulfillment
- `erp-quoting` - Supplier quote collection (RMs) and sell-based margin pricing
- `buyer-quotes` - Consolidated buyer quotes: margins, payment terms, PO file uploads
- `erp-orders` - Buyer and supplier orders derived from accepted quotes
- `erp-shipments` - Fulfillment records: shipments and acceptance reports
- `erp-finance` - Buyer invoices, payments, credit release
- `erp-activity-logging` - Audit event logging incl. line-item activity
- `request-activity-timeline` - Per-request timeline of business events

**Portals & catalog**
- `buyer-portal` - Buyer-facing panel (requests, quotes, invoices)
- `supplier-portal` - Supplier-facing panel (requests, My Articles)
- `public-catalog` - Public product catalog

**Platform**
- `authentication` - Auth including OAuth, 2FA, API tokens
- `team-management` - Multi-team workspace management
- `system-admin` - Administrative panel module
- `custom-fields` - Extensible custom fields system
- `document-storage` - Entity document storage structure
- `import-export` - CSV import/export functionality
- `forms-validation` - Form schemas and validation patterns
- `crud-flows` - Create, Read, Update, Delete operation flows
- `ai-summaries` - AI-powered entity summaries (Prism)

**CRM base (legacy)**
- `crm-core` - Companies, People, Opportunities, Tasks, Notes
```

(d) Key Conventions Summary — replace `5. **CRM entities:** Must use `HasTeam`, `HasCreator` traits` with `5. **Team-scoped entities:** Must use `HasTeam`, `HasCreator` traits`.

(e) Canonical tool form — in the "Quality Gates" section, after the numbered list, add:

```markdown
> **Canonical command form:** `php vendor/bin/<tool>` (Docker wrapper). This
> overrides the Boost pint rule's bare `vendor/bin/pint` form in the managed
> block below — both resolve to the same Docker shim, but always write the
> `php vendor/bin/...` form.
```

- [ ] **Step 3: Verify**

```bash
grep -n '^# ERPC' CLAUDE.md                          # expect 1 hit
grep -c '^- `' CLAUDE.md                             # sanity: spec+rules bullets present
grep -ni 'crm' CLAUDE.md | grep -v -n 'laravel-boost' | head   # manually confirm only: provenance note, crm-core entry, managed-block lines
# spec list completeness — every directory name appears:
for s in $(ls openspec/specs); do grep -q "\`$s\`" CLAUDE.md || echo "MISSING: $s"; done   # expect no output
S=/private/tmp/claude-501/-Users-laurnts-Sites-erpc/92f949b1-6e26-4a24-8834-c7cb4a4df11b/scratchpad
diff "$S/CLAUDE.md.task5-before" CLAUDE.md | grep '^[<>]' | grep -c 'laravel-boost-guidelines'   # expect 0 (managed block untouched)
```

No commit (gitignored).

---

### Task 6: Full verification suite + Boost/Blueprint functional recheck

**Files:**
- Modify: `docs/superpowers/plans/2026-07-14-ai-config-alignment.md` (check off)

**Interfaces:**
- Consumes: everything above.

- [ ] **Step 1: Run every documented command**

```bash
composer test:arch            # PASS
composer test:type-coverage   # PASS
openspec list --specs         # 21 specs
openspec list                 # runs OK
php vendor/bin/pint --dirty   # no-op on clean-ish tree, exits 0
php vendor/bin/rector process --dry-run .claude 2>/dev/null || true   # not required; skip if noisy
composer test                 # full suite — PASS (run once, last; it's slow)
```

- [ ] **Step 2: Boost functional check**

```bash
php artisan boost:mcp --help > /dev/null && echo MCP-OK
grep -c '^=== ' CLAUDE.md     # count guideline sections; expect prior count +2 (filament, prism) vs pre-Task-1
```
Plus: one live `search-docs` MCP query from the session (e.g. "filament resource") returns Filament v5 results — proves the MCP server functions.

- [ ] **Step 3: Blueprint functional check**

```bash
test -f vendor/filament/blueprint/resources/markdown/planning/overview.md && echo BLUEPRINT-DOCS-OK
grep -A3 'filament/blueprint rules' CLAUDE.md   # pointer to overview.md present
```
Read the first ~40 lines of `overview.md` to confirm the pointer target is real, coherent planning guidance.

- [ ] **Step 4: Terminology + containment final gate**

```bash
grep -rni 'crm' .claude/rules/          # exit 1, no output
git status --porcelain                  # only expected tracked changes (plan checkboxes), nothing surprising
```

- [ ] **Step 5: Commit plan checkboxes + report**

```bash
git add docs/superpowers/plans/2026-07-14-ai-config-alignment.md
git commit -m "docs: check off ai-config alignment plan"
```

Report all command outputs faithfully — failures verbatim.

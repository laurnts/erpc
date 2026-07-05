# Tasks: restructure-document-storage

> Sequencing: the authorization fix (1) is a standalone security patch and ships first. Acceptance-report enablement (2) is a prerequisite for team-anchored paths. Path v3 (3) lands behind stamping, so nothing moves until attach sites converge (4) and the migration runs (5).

## 1. Team-scoped document route authorization (DS-4) — independent, ship first

- [x] 1.1 Pin current behavior: tests proving same-team download/delete succeed for `buyer_po` and `quotation` media (goods-receive delete cannot be pinned — it always-404s today due to the morph-type comparison bug; pin the *intended* behavior instead)
- [x] 1.2 Promote the **three** inline delete closures in `routes/web.php` (`:95` buyer PO, `:142` supplier quotation, `:172` goods receive) to invokable controllers matching the download-controller shape; fix the goods-receive morph comparison (`'request'` alias, not the class name) and keep its batch-cleanup behavior
- [x] 1.3 Add team-ownership checks (`$user->belongsToTeam(...)`) to all five endpoints (2 download + 3 delete); cross-tenant → 404; tests: cross-team download/delete rejected on every route, unauthenticated rejected, same-team allowed, goods-receive delete now actually works

## 2. Acceptance report team scoping + media enablement (DS-5)

- [x] 2.1 Migration: add `team_id` (FK, `cascadeOnDelete()`) to `acceptance_reports`, backfill from parent request; replace unique index `(request_id, report_number)` with `(team_id, report_number)` in the same migration
- [x] 2.2 Model: `HasTeam` trait, `acceptance_report` morph alias in `AppServiceProvider` (this repairs the currently silently-failing attachment uploads — `ClassMorphViolationException` swallowed by try/catch), `->useDisk('local')` on the `attachments` collection, register the model in `config/media-library.php` `custom_path_generators`, creating hook assigns `team_id`
- [x] 2.3 Rescope number generation to team+year with `MAX(seq)` semantics (not last-row-by-id; existing numbers untouched); tests: tenancy scoping, backfill, per-team sequence, attachment upload persists a media row on the `local` disk

## 3. Path v3 core (DS-1, DS-2)

- [x] 3.1 Pin v1/v2 resolution: tests asserting `DocumentPathGenerator::getPath()` output for legacy and v2-stamped media before any v3 code lands
- [x] 3.2 New `App\Support\Media\DocumentPathResolver`: computes `documents/team-{id}/{year}/{request_number}/{type}[/{number}]` per model+collection — its map MUST be a strict superset of `DocumentPathGenerator::folderMap()` plus AcceptanceReport — with segment sanitization (`[^A-Za-z0-9._-]` → `-`) and v2 fallback + `Log::warning` when the chain is incomplete; unit tests incl. `qe_number`/`pnl_number` slash cases and the unmapped-model fallback
- [x] 3.3 `AttachUploadedFiles` stamps `path_prefix` + `path_version = 3`; `DocumentPathGenerator` gains the v3 branch (stamped prefix + `{media_id}/`, conversions/responsive under it); tests: stamped resolution is query-free and stable after parent renumbering

## 4. Staging unification + attach convergence (DS-3)

- [x] 4.1 Define `uploads-tmp/{feature}` constants and update all `FileUpload->directory()` call sites (AcceptanceReportResource, staff BuyerQuotes/SupplierQuotes/GoodsReceive/CompletionReports RMs, customer-portal BuyerQuotes RM, SupplierRfqSubmissionForm, CustomerRequestForm, the four `documents-temp` sites); remove duplicated literals (`SubmitSupplierRfqResponse` reuses the form constant)
- [x] 4.2 Reroute **all ten** inline `addMedia()` sites through `AttachUploadedFiles` (authoritative inventory in design.md Decision 4): the four `documents-temp` pages, `AcceptanceReportResource:157`, staff `BuyerQuotesRelationManager:1076`, `SupplierQuotesRelationManager:1657`, `GoodsReceiveRelationManager:192`, `CompletionReportsRelationManager:182,206`, and `SeedRequestDocumentsCommand:226` (v3 stamping; update its test if it pins v2); tests: traversal outside the staging dir rejected, attached media lands v3-stamped
  - DEVIATION: `SeedRequestDocumentsCommand` was NOT converged — at implementation time it was an uncommitted file from a concurrent session, so modifying it would have hijacked pending work. Follow-up required once it lands: route its `seedMedia()` through `AttachUploadedFiles` (or stamp v3) and replace `tempnam` (pre-existing ArchTest failure). Until then dev-seeded documents are v2 (migratable via `documents:migrate-v3`).
- [x] 4.3 Upload→attach feature tests per flow (staff buyer PO, staff supplier quotation, supplier RFQ submission, customer request attachments) green with new staging dirs

## 5. Migration + orphan hygiene (DS-6)

- [x] 5.1 `documents:migrate-v3` command: idempotent move+stamp of pre-v3 local-disk documents, driven by a DB query (no hardcoded counts); test with fabricated v2 media proving file move, stamp, and skip-on-rerun
- [x] 5.2 `documents:scan-orphans` command: referenced set built per disk from each media row's generator-resolved path (id-folder presence is insufficient — see design.md Decision 6), skips `uploads-tmp/` and `livewire-tmp/`, report-only default, `--delete` flag; test detects a planted orphan and preserves referenced files, including the id-collision case (orphan `public/{id}` vs live local media `{id}`)
- [x] 5.3 Run both against the live store (migrate count taken from the DB; known orphans `attachments/1/`, `public/1/favicon.svg`, `public/2/relaticle-logomark.svg`); verify buyer-PO and supplier-quotation downloads still work post-move

## 6. Validation

- [x] 6.1 `vendor/bin/pint --dirty`, PHPStan, and the affected suites (Media, Portal, upload/download feature tests, seeder test) green
- [x] 6.2 `openspec validate restructure-document-storage --strict` passes; archive after deploy

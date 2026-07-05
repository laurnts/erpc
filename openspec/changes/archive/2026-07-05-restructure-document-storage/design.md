# Design: restructure-document-storage

## Context

Spatie Media Library calls `PathGenerator::getPath(Media)` on **every** file access (serve, move, delete, conversions). Whatever the generator returns must be stable for the life of the file — and cheap, because it runs outside any eager-loading context. The current `DocumentPathGenerator` already handles this with a versioning scheme: legacy media resolve to `{id}/`, v2 media to `{folder}/{id}/uploaded_document_files/`. The system holds 7 media rows total (6 private documents, 1 public product image — count from the DB at migration time, the seeder keeps adding), so migration cost is at its floor. `moves_media_on_update` is `false` in `config/media-library.php`, so a stamped prefix plus an explicit migration move is the correct mechanism — Spatie will not relocate files on its own.

## Goals / Non-Goals

**Goals**
- A human browsing `storage/app/documents/` can identify team → year → deal → document type → document without the database.
- Path resolution stays O(1) with zero DB queries.
- Existing v1/v2 media keep resolving byte-identically.
- Document routes enforce team ownership.

**Non-Goals**
- Public media (product images, team branding, profile photos) — already correctly on the `public` disk, not audit material.
- S3 support (the disk is configured but unused; the layout is disk-agnostic anyway).
- Retention policies / WORM storage — out of scope; layout is a prerequisite, not a substitute.
- Renaming folders when a model is renumbered or a team renamed (see Decision 1).
- Fixing the broken `getUrl()`-based document lists (`document-list.blade.php`, `goods-receive-document-list.blade.php`, `CompletionReportsRelationManager`, `CreditLimitAcceptanceResource`): local-disk media has no `/storage` URL, so those previews 404 today and continue to after this change (no regression — the files were never reachable that way). A follow-up change should add a generic authorized download route; DS-4 here covers only the routes that exist.

## Decision 1: Stamp the path at attach time; never derive at read time

**Choice**: `AttachUploadedFiles` computes the full directory prefix once (via `DocumentPathResolver`) and writes it to `custom_properties.path_prefix` with `path_version = 3`. `DocumentPathGenerator::getPath()` returns the stamped prefix + `{media_id}/`.

**Why not derive live in the generator** (load `$media->model`, walk to request/team)? Three failure modes:
1. A DB query (or several — model → request → team) per path resolution, on every media touch.
2. Deleted or renumbered parent models silently change/break paths to files already on disk.
3. `getPath()` must work during deletion cascades when the parent row may already be gone.

The stamped path is a frozen snapshot of business context at upload time — which is what auditors want: documents don't retroactively move. The DB row is also self-documenting (`path_prefix` readable in the `media` table). This extends the existing `path_version` pattern rather than inventing a parallel mechanism.

**Consequence accepted**: if a request is renumbered after upload (doesn't happen today — numbers are observer-generated once), old files stay under the old number. Correct for audit; documented here so nobody "fixes" it.

## Decision 2: Layout anchored on the Request (the deal)

```
documents/
└── team-{team_id}/
    └── {year}/                        ← anchoring request's created year
        └── {request_number}/          ← REQ-2026-0001
            ├── request-attachments/{media_id}/{file}
            ├── goods-receive/{media_id}/{file}
            ├── completion-reports/{media_id}/{file}
            ├── supplier-quotes/{quote_number}/{media_id}/{file}
            ├── buyer-quotes/{quote_number}/{media_id}/{file}
            ├── supplier-orders/{po_number}/{media_id}/{file}
            ├── quotation-evaluations/{qe_number*}/{media_id}/{file}
            ├── profit-and-loss/{pnl_number*}/{media_id}/{file}
            └── acceptance-reports/{report_number}/{media_id}/{file}
```
`*` = sanitized (see Decision 3).

- **Why request-anchored, not model-anchored**: a procurement audit walks a transaction — pick a deal, verify its document chain (RFQ → quote → evaluation → PO → goods receive → acceptance → P&L). Every private-document model carries `request_id` (AcceptanceReport via its request until DS-5 gives it `team_id` directly).
- **Why `team-{id}` not a name slug**: `Team` has no slug/code column (`teams`: id, user_id, name, personal_team); names are mutable and non-unique. A stamped name-slug was considered and rejected — two teams named "Acme" would collide, and renames would make sibling folders inconsistent over time. The numeric id is stable and still segregates cleanly.
- **Why the `{media_id}` leaf stays**: uniqueness when the same filename uploads twice, plus a home for Spatie conversions (`…/{media_id}/conversions/`). With readable ancestors, the id no longer costs navigability.
- **Year source**: the anchoring request's `created_at->year` — same year its observer baked into `request_number`, so folder and number never disagree.
- **Versioned buyer quotes are a non-issue**: `BuyerQuote::createNewVersion()` generates a *fresh* `quote_number` per version, so versions get their own folders; even a number collision would be disambiguated by the `{media_id}` leaf.
- **Fallback**: if the resolver cannot build a full prefix (missing request relation — should not occur, but the resolver must not throw during a save), the attach stamps **v2** semantics — which requires the resolver's model+collection map to be a strict superset of `folderMap`, otherwise "v2 fallback" for an unmapped model degenerates to the bare legacy `{id}/` at the storage root, silently recreating the mess DS-1 removes. Every fallback fires a `Log::warning` so degraded attaches are noticed. A document landing in the v2 layout is degraded-but-correct; an exception during attach loses the upload.

## Decision 3: Sanitization of Indonesian-format numbers

`qe_number` (`001-DS/QE/VII/2026`) and `pnl_number` (`0001/EL-PNL/VII/2026`) contain `/`. Used raw they would each create four nested directories. The resolver slugs every path segment: any character outside `[A-Za-z0-9._-]` becomes `-` (so `001-DS-QE-VII-2026`). Applied uniformly to all segments as defense-in-depth, not just the known-bad two.

## Decision 4: One staging root, one attach path

All Filament `FileUpload->directory()` values become `uploads-tmp/{feature}` (e.g. `uploads-tmp/supplier-quotes`, `uploads-tmp/buyer-po`), each defined **once** as a constant on the owning model/form class — the pattern `BuyerQuote::PO_FILES_UPLOAD_DIRECTORY` already established. This fixes three things at once:
1. Duplicated hardcoded strings (`supplier-quotes/quotation` lives in both `SupplierRfqSubmissionForm.php:89` and `SubmitSupplierRfqResponse.php:69`) — drift the code comments already warn about.
2. The `documents-temp` grab-bag shared by four unrelated features.
3. Inline `$record->addMedia($path)` on Livewire-supplied paths bypasses the realpath traversal guard in `AttachUploadedFiles:37-39` — and this is **not** just the four `documents-temp` sites. The authoritative inventory of inline attach sites that must converge on the action (they also otherwise keep minting v2 paths after v3 ships):
   - `ViewQuotationEvaluation:146`, `SupplierOrderApprovalResource:160`, `ViewSupplierOrderApproval`, `ViewProfitAndLoss:146` (documents-temp)
   - `AcceptanceReportResource:157` (Livewire state paths, `file_exists` check only)
   - `RequestResource/RelationManagers/BuyerQuotesRelationManager:1076` and `SupplierQuotesRelationManager:1657` (staff uploads — the highest-traffic flows)
   - `GoodsReceiveRelationManager:192`, `CompletionReportsRelationManager:182,206`
   - `Console/Commands/SeedRequestDocumentsCommand:226` (stamps v2 explicitly; must stamp v3 or route through the action)

`storage/app` then contains `documents/` (canonical, backed by media rows), `uploads-tmp/` (transient, prunable), and framework directories (`livewire-tmp/`, the `public/` disk root) — the orphan scanner must not treat the framework directories as document space.

## Decision 5: Authorization = team membership on the owning record

Five routes are in scope: two download controllers (`BuyerQuotePoDownloadController`, `SupplierQuoteQuotationDownloadController`) and **three** inline delete closures in `routes/web.php` (buyer PO `:95`, supplier quotation `:142`, goods receive `:172`). All get: current user must belong to the owning record's team (`$user->belongsToTeam(...)` — provided by Jetstream `HasTeams`). The delete closures are promoted to invokable controllers matching the existing download-controller shape. The goods-receive closure is additionally a live functional bug: it compares `$media->model_type !== Request::class` while the enforced morph map stores `'request'`, so it always 404s — the promotion fixes the comparison.

Verified callers: staff-panel blades only — `buyer-po-list.blade.php`, `supplier-quote-quotation-list.blade.php`, and `goods-receive-document-list.blade.php` (rendered by `GoodsReceiveRelationManager:250` and `GoodsReceiveApprovalResource:226`). Neither portal uses these routes, so team membership is the complete rule; no portal carve-out needed. Failure mode is 404 (not 403) to avoid confirming document existence across tenants.

## Decision 6: Migration + orphan hygiene as artisan commands

- `documents:migrate-v3`: for each v1/v2 media on the `local` disk (count from the DB — no hardcoded totals), compute the v3 prefix, physically move the directory, stamp `path_prefix`/`path_version` — one transaction per media, idempotent (skips already-stamped rows). Runs once; kept for installs that upgrade later.
- `documents:scan-orphans`: builds the referenced-file set **per disk from each media row's generator-resolved path** (`$media->getPath()` relative to its disk), then walks the disks and reports anything outside that set, `uploads-tmp/`, and `livewire-tmp/`. Id-folder presence is NOT sufficient: orphan `public/1/favicon.svg` shares its folder id with live *local*-disk media id 1 — a naive id-existence check would call it referenced. Known orphans today: `attachments/1/`, `public/1/favicon.svg`, `public/2/relaticle-logomark.svg`. `--delete` removes; report-only by default; `uploads-tmp` is never touched by this command.

## Decision 7: AcceptanceReport enablement is more than a `team_id` column

Acceptance-report attachments **silently fail today**: the model is absent from the enforced morph map, so `addMedia()` throws `ClassMorphViolationException`, which the surrounding try/catch swallows into a log (confirmed: zero acceptance media rows). Making the DS-1 `acceptance-reports/` branch real requires four coordinated pieces, not one:
1. `acceptance_report` morph alias in the enforced morph map (fixes the live bug).
2. `->useDisk('local')` on the model's media collection — without it, `MEDIA_DISK` defaults to `public` and the path generator's `disk !== 'local'` short-circuit skips dedicated paths entirely.
3. Registration in `config/media-library.php` `custom_path_generators` (per-model-class mapping; AcceptanceReport is currently absent, so it would use `DefaultPathGenerator` no matter what the v3 branch does).
4. A `DocumentPathResolver` mapping entry.

Numbering rescope (request+year → team+year) must also move the unique index from `(request_id, report_number)` to `(team_id, report_number)` and use `MAX(seq)`-style next-number semantics — the current "last row by id, +1" pattern can duplicate once historical per-request sequences coexist with the team-wide one. The table is empty today, so both are cheap now.

## Risks / Trade-offs

- **Regression risk on existing media resolution**: mitigated by pinning v1/v2 `getPath()` outputs with tests *before* the v3 branch lands.
- **Stamped paths go stale relative to business data**: accepted deliberately (Decision 1). The `media` table remains the source of truth for lookup; the path is for humans.
- **AcceptanceReport numbering rescope** (request+year → team+year) changes future sequences only; existing report numbers are never rewritten.
- **Deep paths** (~7 levels): well within filesystem limits; trailing `{media_id}` keeps every leaf unique regardless of business-number collisions across teams (numbers are only unique per team).

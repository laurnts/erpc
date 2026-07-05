# document-storage Specification

## Purpose
TBD - created by archiving change restructure-document-storage. Update Purpose after archive.
## Requirements
### Requirement: Audit-Navigable Document Path Structure
Private business documents SHALL be stored under a human-readable hierarchy `documents/team-{team_id}/{year}/{request_number}/{document-type}[/{document-number}]/{media_id}/{filename}` on the private disk, so that a deal's complete document chain is identifiable from the directory structure alone.

#### Scenario: Supplier quotation storage path
- **WHEN** a quotation file is attached to supplier quote `SQ-2026-0003` belonging to request `REQ-2026-0001` of team 1
- **THEN** the file is stored under `documents/team-1/2026/REQ-2026-0001/supplier-quotes/SQ-2026-0003/{media_id}/`
- **AND** the year segment matches the anchoring request's creation year

#### Scenario: Request-level collections
- **WHEN** files are attached to a request's `attachments`, `goods_receive`, or `completion_reports` collections
- **THEN** they are stored under the request folder in `request-attachments/`, `goods-receive/`, or `completion-reports/` respectively

#### Scenario: Business numbers containing path separators are sanitized
- **WHEN** a document is attached to quotation evaluation `001-DS/QE/VII/2026`
- **THEN** the path segment is sanitized to `001-DS-QE-VII-2026` (characters outside `[A-Za-z0-9._-]` replaced with `-`)
- **AND** no additional directory nesting is created by the separators

#### Scenario: Public media unaffected
- **WHEN** a product image, team logo, or profile photo is uploaded
- **THEN** it continues to store on the `public` disk outside the `documents/` hierarchy

### Requirement: Path Stamped at Attach Time
The system SHALL compute each document's directory prefix once at attach time and persist it on the media record (`custom_properties.path_prefix`, `path_version = 3`); path resolution SHALL read the stamped value without querying related models. Media stamped with earlier path versions SHALL continue to resolve to their existing locations unchanged.

#### Scenario: Stamped prefix used for resolution
- **WHEN** the path generator resolves a media item with `path_version = 3`
- **THEN** it returns the stamped `path_prefix` plus the media id segment
- **AND** performs no database queries against the owning model or its relations

#### Scenario: Renumbered parent does not move files
- **WHEN** a parent record's business number changes after a document was attached
- **THEN** the stored file remains at its original stamped path and stays downloadable

#### Scenario: Legacy media resolve unchanged
- **WHEN** the path generator resolves media stamped with `path_version = 2` or with no version property
- **THEN** the resolved path is identical to the pre-v3 behavior

#### Scenario: Resolver fallback never loses an upload
- **WHEN** the v3 prefix cannot be computed at attach time (e.g. missing request relation)
- **THEN** the file is attached with v2 path semantics instead of failing the save
- **AND** a warning is logged so the degraded attach is noticed

### Requirement: Unified Upload Staging Area
All Filament file-upload staging directories SHALL live under a single `uploads-tmp/{feature}` root, with each directory string defined exactly once as a class constant, and all attach flows SHALL pass through the guarded `AttachUploadedFiles` action.

#### Scenario: Staging separated from canonical storage
- **WHEN** inspecting `storage/app`
- **THEN** transient upload staging is confined to `uploads-tmp/`
- **AND** canonical documents are confined to `documents/`

#### Scenario: No duplicated directory strings
- **WHEN** a form component and its submit action reference the staging directory
- **THEN** both reference the same class constant

#### Scenario: All attaches are traversal-guarded
- **WHEN** any feature converts staged upload paths into media (staff buyer PO and supplier quotation uploads, goods receive, completion reports, acceptance reports, quotation evaluations, supplier order approvals, profit-and-loss documents, portal submissions, and the document seeder)
- **THEN** the paths pass through `AttachUploadedFiles`, which rejects any path resolving outside its declared staging directory
- **AND** the resulting media is stamped with the v3 path

### Requirement: Team-Scoped Document Route Authorization
Document download and delete routes SHALL verify that the authenticated user belongs to the team owning the target record, responding 404 to cross-tenant attempts.

#### Scenario: Cross-team download rejected
- **WHEN** an authenticated user of team B requests the download route for a buyer PO or supplier quotation belonging to team A
- **THEN** the response is 404
- **AND** the file contents are not served

#### Scenario: Cross-team delete rejected
- **WHEN** an authenticated user of team B requests the delete route for a document belonging to team A
- **THEN** the response is 404
- **AND** the media record and file remain intact

#### Scenario: Same-team access allowed
- **WHEN** a user belonging to the owning team requests download or delete
- **THEN** the operation succeeds as before

#### Scenario: Goods-receive delete route is functional and scoped
- **WHEN** a same-team user deletes a goods-receive document via its delete route
- **THEN** the deletion succeeds (the morph-type comparison uses the `request` alias, fixing the current always-404)
- **AND** cross-team attempts receive 404

### Requirement: Legacy Migration and Orphan Hygiene
The system SHALL provide an idempotent command migrating pre-v3 document media to the v3 layout, and a command that reports (and optionally deletes) files on document disks that no media record references.

#### Scenario: Migrate existing documents
- **WHEN** `documents:migrate-v3` runs
- **THEN** each un-stamped local-disk document is moved to its v3 path and stamped with `path_prefix` and `path_version = 3`
- **AND** re-running the command skips already-stamped media

#### Scenario: Orphan scan is report-only by default
- **WHEN** `documents:scan-orphans` runs without flags
- **THEN** files not present in the referenced set are listed but not removed
- **AND** the referenced set is built per disk from each media record's resolved path (a folder merely sharing an id with a media row on another disk is still an orphan)

#### Scenario: Orphan deletion is explicit
- **WHEN** `documents:scan-orphans --delete` runs
- **THEN** orphaned files are removed
- **AND** `uploads-tmp/` and framework temp directories are never touched by this command

### Requirement: Generic Authorized Document Download
The system SHALL provide a single authenticated route (`documents.download`) serving any private document by media id, authorizing via the owning model's team, so every document type has a working, team-scoped retrieval path.

#### Scenario: Same-team download succeeds
- **WHEN** an authenticated user belonging to the owning model's team requests `/documents/{media}`
- **THEN** the file is served inline with its stored mime type and file name

#### Scenario: Cross-tenant access rejected
- **WHEN** an authenticated user of another team requests the route
- **THEN** the response is 404 and no file content is served

#### Scenario: Unresolvable ownership rejected
- **WHEN** the media's owning model is missing or carries no team
- **THEN** the response is 404

#### Scenario: Document lists use the route
- **WHEN** a document list renders a private (local-disk) media item
- **THEN** its link targets `documents.download` instead of the non-functional `/storage` URL


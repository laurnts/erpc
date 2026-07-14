# document-storage Delta

## MODIFIED Requirements

### Requirement: Generic Authorized Document Download
The system SHALL provide a single authenticated route (`documents.download`) serving any private document by media id, authorizing via the owning model's team, so every document type has a working, team-scoped retrieval path. The route SHALL accept an explicit `download` query flag that forces `Content-Disposition: attachment`; without the flag, the existing inline behaviour for render-safe mime types is unchanged.

#### Scenario: Same-team download succeeds
- **WHEN** an authenticated user belonging to the owning model's team requests `/documents/{media}`
- **THEN** the file is served inline with its stored mime type and file name

#### Scenario: Forced download serves every type as an attachment
- **WHEN** a same-team user requests `/documents/{media}?download=1`
- **THEN** the response carries `Content-Disposition: attachment` for every file type, including render-safe mimes that would otherwise serve inline
- **AND** render-safe mimes keep their stored mime type while unsafe types remain `application/octet-stream`

#### Scenario: Cross-tenant access rejected
- **WHEN** an authenticated user of another team requests the route
- **THEN** the response is 404 and no file content is served

#### Scenario: Unresolvable ownership rejected
- **WHEN** the media's owning model is missing or carries no team
- **THEN** the response is 404

#### Scenario: Document lists use the route
- **WHEN** a document list renders a private (local-disk) media item
- **THEN** its link targets `documents.download` instead of the non-functional `/storage` URL

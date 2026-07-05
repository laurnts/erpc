# document-storage Spec Delta

## ADDED Requirements

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

# document-storage Delta

## ADDED Requirements

### Requirement: Portal-Authorized Document Download
The system SHALL provide panel-scoped document download routes for portal parties (`/{buyer_path}/documents/{media}` named `buyer.documents.download`, `/{supplier_path}/documents/{media}` named `supplier.documents.download`), living under the panel path prefixes so each portal's session cookie applies, guarded by the panel's auth guard, and authorized fail-closed: the media's owning model must resolve to a request in the portal user's team, and the media must pass the same subject allow-list and media rules (`PortalTimelineSource::allowsMedia`) that govern what the party's timeline shows. Responses SHALL always force `Content-Disposition: attachment`.

#### Scenario: Buyer downloads a timeline-visible document
- **WHEN** an authenticated buyer portal user requests the buyer document route for media their timeline shows (e.g. a buyer-stamped request attachment)
- **THEN** the file is served as an attachment

#### Scenario: Party cannot fetch media its timeline hides
- **WHEN** a buyer portal user requests media that fails the buyer media rules (e.g. a staff-uploaded supplier quotation, or unstamped media)
- **THEN** the response is 404 and no file content is served

#### Scenario: Cross-tenant and cross-company access rejected
- **WHEN** a portal user requests media belonging to another team, or scoped to another company's subjects on a shared request
- **THEN** the response is 404

#### Scenario: Unauthenticated portal request is redirected
- **WHEN** an unauthenticated visitor requests a portal document route
- **THEN** they are redirected to the portal login

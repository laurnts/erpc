# request-activity-timeline Delta

## RENAMED Requirements

- FROM: `### Requirement: Internal Media Entry Download Links`
- TO: `### Requirement: Media Entry Download Links`

## MODIFIED Requirements

### Requirement: Media Entry Download Links

Timeline media entries SHALL render the uploaded file name — and only the file name, not the surrounding headline text — as a link that downloads the file with identical behaviour for every file type. Internal (staff) entries SHALL link to the generic authorized document download route with the forced-download flag. Portal (buyer/supplier) entries SHALL link to their panel-scoped portal document download route, and every portal link SHALL pass the party's default-deny link allow-list (`RedactionRules::allowsLinkRoute`) by route name; an entry whose link route is not allow-listed renders as plain text.

#### Scenario: Staff downloads an uploaded file from the timeline
- **WHEN** a staff user clicks the file name in an upload entry ("uploaded {file} → {collection}") on the internal request timeline
- **THEN** the browser downloads the file via `documents.download` with the forced-download flag, regardless of file type (PDF, Word, Excel, image)

#### Scenario: Only the file name is hyperlinked
- **WHEN** any timeline renders an upload entry that carries a link
- **THEN** only the file name segment is wrapped in the anchor; the "uploaded" verb and the collection label render as plain text

#### Scenario: Portal party downloads a visible upload
- **WHEN** a buyer or supplier party clicks the file name of a media entry their timeline shows
- **THEN** the file downloads via their panel-scoped portal document route, which re-authorizes fail-closed with the same media visibility rules the timeline applied

#### Scenario: Entries without a link stay plain text
- **WHEN** a timeline renders a media entry whose link route is not on the party's allow-list (or that carries no link)
- **THEN** the entry renders as plain text exactly as before

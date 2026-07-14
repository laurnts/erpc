# request-activity-timeline Delta

## ADDED Requirements

### Requirement: Internal Media Entry Download Links

Internal (staff) timeline media entries SHALL link to the generic authorized document download route with the forced-download flag, so clicking an upload entry downloads the file with identical behaviour for every file type. Portal timeline media entries SHALL NOT carry a document link until a portal-authorized document route exists, because portal parties cannot pass the team-membership check on the generic route.

#### Scenario: Staff downloads an uploaded file from the timeline
- **WHEN** a staff user clicks an upload entry ("uploaded {file} → {collection}") on the internal request timeline
- **THEN** the browser downloads the file via `documents.download` with the forced-download flag, regardless of file type (PDF, Word, Excel, image)

#### Scenario: Entry renders as a link only when a URL is present
- **WHEN** the internal timeline renders an entry whose `url` is set
- **THEN** the headline renders as a link to that URL, matching the portal timeline's existing link markup
- **AND** entries without a `url` render as plain text exactly as before

#### Scenario: Portal media entries remain plain text
- **WHEN** a buyer or supplier party views their portal timeline
- **THEN** media entries carry no document link and render as plain text

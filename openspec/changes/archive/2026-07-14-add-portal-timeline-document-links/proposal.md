# Add Portal Timeline Document Links

## Why

The just-shipped internal timeline download links (`add-timeline-media-download-links`, archived 2026-07-14) hyperlink the entire "uploaded {file} → {collection}" headline, and portal timelines still render uploads as dead text. User feedback: only the file name should be the link, and buyer/supplier portal users should be able to download timeline-visible documents too — permission-gated. Portals cannot use the generic `documents.download` route (portal users are not team members, and the route lives outside the panel session-cookie prefixes), and the portal redaction layer (`RedactionRules::allowsLinkRoute`, default deny) strips any link whose route is not allow-listed — which is the designed extension point this change uses.

## What Changes

- Timeline upload entries hyperlink **only the file name**; the "uploaded" verb and collection label stay plain text. Applies to the internal timeline and both portal timelines.
- New panel-scoped portal document download routes — `/{buyer_path}/documents/{media}` (`buyer.documents.download`) and `/{supplier_path}/documents/{media}` (`supplier.documents.download`) — under the panel path prefixes (so `UsePanelSession` applies the right session cookie), guarded by the panel auth guards, always responding `Content-Disposition: attachment`.
- Authorization is fail-closed and reuses the timeline's own visibility rules via a new `PortalTimelineSource::allowsMedia()`: the media's owning model must resolve to a request in the portal user's team, sit in the party's allow-listed subject set (identity-scoped), and pass the party's `MediaRule`.
- `PortalTimelineSource::mediaEntries()` sets the party-appropriate link; the link's route name flows through the existing default-deny `allowsLinkRoute` check (buyer/supplier allow-lists gain the `buyer.documents.` / `supplier.documents.` prefixes).

## Impact

- Affected specs: `request-activity-timeline` (rename + modify the media-link requirement), `document-storage` (new portal download requirement)
- Affected code:
  - `app/Services/Timeline/RequestTimelineSource.php`, `PortalTimelineSource.php` (media entry link + file-name properties; `allowsMedia()`)
  - `app/Services/Timeline/TimelineAudience.php` (link-route allow-lists)
  - `app/Http/Controllers/PortalDocumentDownloadController.php` (new)
  - `routes/web.php` (two new routes)
  - `resources/views/livewire/request-history-timeline.blade.php`, `resources/views/timeline/portal-timeline.blade.php` (filename-only anchor)
- No schema changes; no new dependencies.

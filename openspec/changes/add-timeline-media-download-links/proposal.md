# Add Timeline Media Download Links

## Why

On the internal request timeline, an upload renders as plain text ("uploaded placeholder-quotation.pdf → Quotation") with no way to open the file — staff must hunt for the document on the relevant tab. The `TimelineEntry` DTO already has an allow-listed `url` field (the portal blade even renders it as a link), and the team-scoped `documents.download` route already serves any private document by media id; the two were never wired together for media entries.

## What Changes

- Internal timeline media entries carry a link to the generic `documents.download` route with a forced-download flag, so clicking any uploaded file (PDF, Word, Excel, image) downloads it — one uniform behaviour for every type.
- `documents.download` accepts an explicit `?download=1` query flag that forces `Content-Disposition: attachment` (default inline behaviour for render-safe mimes is unchanged, as are the other download controllers).
- The internal timeline blade renders the headline as a link when an entry has a `url`, mirroring the portal timeline's existing markup.
- Portal timelines are explicitly unchanged: `PortalTimelineSource` does not set `url` on media entries because portal users are not team members and cannot pass the `documents.download` team check (portal-scoped document routes, thumbnails, and a preview modal are documented follow-ups, out of scope here).

## Impact

- Affected specs: `request-activity-timeline` (media entries become links, internal only), `document-storage` (forced-download flag on the generic route)
- Affected code:
  - `app/Services/Timeline/RequestTimelineSource.php` (set `url` in `mediaEntries()`)
  - `app/Http/Controllers/DocumentDownloadController.php` (read the flag)
  - `app/Support/Media/DocumentResponse.php` (opt-in `forceDownload` parameter; other call sites unchanged)
  - `resources/views/livewire/request-history-timeline.blade.php` (link markup)
- No schema, route, or portal changes; no new dependencies.

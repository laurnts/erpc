# Tasks

## 1. Filename-only links
- [x] 1.1 Both timeline sources put `file_name` and `collection_label` into media entry `properties`
- [x] 1.2 Internal timeline blade and shared portal timeline blade render media entries as `uploaded <a>{file_name}</a> → {collection_label}` when a link is present, plain headline otherwise
- [x] 1.3 Update the internal Livewire render test to assert only the file name is inside the anchor

## 2. Portal document download route
- [x] 2.1 `PortalDocumentDownloadController`: resolve portal context (buyer/supplier by route default), resolve the owning request from the media's model, verify team, then `PortalTimelineSource::allowsMedia()` fail-closed; serve via `DocumentResponse::make(..., forceDownload: true)`
- [x] 2.2 Add `PortalTimelineSource::allowsMedia(Request, TimelineParty, Media)` reusing `allowedSubjects()` + `mediaPasses()`
- [x] 2.3 Register `/{buyer_path}/documents/{media}` and `/{supplier_path}/documents/{media}` routes (`web` + `auth:buyer|supplier`), named `buyer.documents.download` / `supplier.documents.download`
- [x] 2.4 Feature tests: buyer downloads buyer-visible media as attachment; buyer denied staff-only/unstamped media (404); cross-team 404; unauthenticated redirect; supplier equivalents

## 3. Portal timeline links
- [x] 3.1 `PortalTimelineSource::mediaEntries()` sets the party-appropriate download URL and records the link route name for the redaction check
- [x] 3.2 `redact()` gates the URL through `allowsLinkRoute` using the recorded route name; buyer/supplier `RedactionRules` allow `buyer.documents.` / `supplier.documents.` prefixes
- [x] 3.3 Update portal source test: media entries now carry the portal download URL (replacing the no-link guard test); leak tests still pass

## 4. Quality gates
- [x] 4.1 `php vendor/bin/rector process` + `php vendor/bin/pint --dirty` on changed files, affected test files green, `openspec validate add-portal-timeline-document-links --strict`

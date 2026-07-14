# Tasks

## 1. Forced-download flag on the generic document route
- [x] 1.1 Add an opt-in `bool $forceDownload = false` parameter to `DocumentResponse::make()` that forces `Content-Disposition: attachment` while preserving the existing mime handling (stored mime for render-safe types, `application/octet-stream` otherwise); the other call sites (`BuyerQuotePoDownloadController`, `SupplierQuoteQuotationDownloadController`) stay untouched
- [x] 1.2 Pass `$request->boolean('download')` through in `DocumentDownloadController`
- [x] 1.3 Feature tests: `?download=1` returns `attachment` disposition for a PDF (render-safe) and keeps existing inline behaviour without the flag; existing cross-team 404 coverage still passes

## 2. Timeline media entries link to the download
- [x] 2.1 In `RequestTimelineSource::mediaEntries()`, set `url` to `route('documents.download', ['media' => $item, 'download' => 1])` on each media entry
- [x] 2.2 In `resources/views/livewire/request-history-timeline.blade.php`, render the headline as a link when `$entry->url` is set (mirror the portal blade's link markup), plain text otherwise
- [x] 2.3 Tests: timeline source test asserts media entries carry the download URL (and portal source media entries still carry none); Livewire render test asserts the upload entry renders as a link

## 3. Quality gates
- [x] 3.1 `php vendor/bin/rector process` + `php vendor/bin/pint --dirty` on changed files, run affected test files, `openspec validate add-timeline-media-download-links --strict`

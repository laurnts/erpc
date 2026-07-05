# Change: Add a generic authorized document download route

## Why

Several document types render download links via `$media->getUrl()`, which for private local-disk media produces a `/storage/…` URL that always 404s — quotation evaluation documents, P&L documents, supplier-order approval documents, goods-receive documents, completion reports, and credit-limit acceptance files have no working preview/download today. This was declared a known limitation in `restructure-document-storage`; only buyer-PO and supplier-quotation files have (route-based, team-authorized) downloads. An audit-structured store whose documents can't be retrieved through an authorized path is only half done.

## What Changes

- **DD-1 Generic authorized download route**: `GET /documents/{media}` (`documents.download`, `web`+`auth` middleware) served by a `DocumentDownloadController` that authorizes generically: the media's owning model is resolved via the enforced morph map, the current user must be a `User` belonging to the owning model's team (`team_id`), and failures — unknown media, model without a team, cross-tenant, missing file — all yield 404. Serves inline (`response()->file`) with the media's mime type and file name, matching the two existing specialized download controllers.
- **DD-2 Broken `getUrl()` call sites switch to the route**: `resources/views/filament/infolists/components/document-list.blade.php`, `resources/views/filament/forms/components/goods-receive-document-list.blade.php`, `app/Filament/Resources/RequestResource/RelationManagers/CompletionReportsRelationManager.php`, and `app/Filament/Resources/BuyerCreditLimitRequests/.../CreditLimitAcceptanceResource.php` (verify exact path) render `route('documents.download', $media)` for local-disk media instead of `getUrl()`. Public-disk media (images rendered inline) keep `getUrl()`.
- The two existing specialized routes (buyer PO, supplier quotation) remain — their blades also carry delete actions wired to the specialized delete routes.

## Impact

- Affected specs: `document-storage` (ADDED: Generic Authorized Document Download)
- Affected code: new `app/Http/Controllers/DocumentDownloadController.php`, `routes/web.php`, 2 blades + 2 Filament resources
- Tests: authorization matrix (same-team 200, cross-team 404, unauthenticated redirect/404, model-without-team 404, missing file 404) modeled on `tests/Feature/Http/DocumentRouteAuthorizationTest.php`

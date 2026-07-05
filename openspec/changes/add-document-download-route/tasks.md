# Tasks: add-document-download-route

- [ ] 1.1 `DocumentDownloadController` (`final readonly`, `__invoke(Media $media)`): resolve owning model via morph map; require `auth()->user() instanceof User` and `belongsToTeam($model->team)`; 404 on unknown media/model, teamless model, cross-tenant, or missing file; serve `response()->file` inline. Route `GET /documents/{media}` name `documents.download` under `web`+`auth`. TDD: authorization-matrix tests first (same-team, cross-team, unauthenticated, teamless owner, missing file)
- [ ] 1.2 Switch broken `getUrl()` sites to `route('documents.download', $media)` for local-disk media: `document-list.blade.php`, `goods-receive-document-list.blade.php`, `CompletionReportsRelationManager`, credit-limit acceptance resource; leave public-disk image rendering on `getUrl()`
- [ ] 1.3 `php vendor/bin/pint --dirty`; focused suites green; `openspec validate add-document-download-route --strict`

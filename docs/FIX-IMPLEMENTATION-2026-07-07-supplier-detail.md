# Bugfix Implementation - Supplier Detail Articles Tab - 2026-07-07

## 1. Module Overview

- Document Version: 1.0 (Laravel 12 / Filament 5)
- Environment: Production (`app-erpc.bpdn.id`, `erpc.bpdn.id`)
- Core Purpose: Fix supplier detail page error when lazy-loading the Articles relation manager tab.
- Main Fixes:
  - Fix 4: Supplier view page shows "Error while loading page" toast; Articles / Portal Users tabs fail to render.

## 2. Technical Structure

### Dependencies

- Laravel Framework ^12.0
- Filament ^5.0
- `filament/spatie-laravel-media-library-plugin` ^5.0 (declared in `composer.json`; required for full Article form image uploads)
- `Relaticle\CustomFields` (supplier infolist; unchanged)

### Configuration

No `.env` or config changes are required for this fix.

Optional follow-up if the full Article create/edit form with product images should work:

```bash
docker exec erpc-php composer install --no-interaction
```

### Core Files

- `app/Filament/Resources/SupplierResource/Pages/ViewSupplier.php`: Supplier detail page (`/{tenant}/suppliers/{record}`).
- `app/Filament/Resources/SupplierResource/RelationManagers/ArticlesRelationManager.php`: Articles tab; calls `ArticleResource::getFormSchema(forModal: true)` for inline Create action.
- `app/Filament/Resources/ArticleResource.php`: Shared article form schema; fixed lazy section building.
- `tests/Feature/Filament/App/Resources/ViewSupplierPageTest.php`: Regression tests for supplier view page and relation managers.

### Core Methods

```php
public static function getFormSchema(bool $forModal = false): array
public function table(Table $table): Table
```

## 3. Implementation Details

### Supplier Detail Articles Tab Error

#### Symptom

- URL: `https://app-erpc.bpdn.id/{team}/suppliers/{id}` (for example supplier IBOX, code `CMP-0016`)
- Supplier header/infolist loads correctly.
- Filament toast appears: "Error while loading page. There was an error while attempting to load this page. Please try again later."
- Articles and Portal Users tab content area stays blank.
- Observed: 2026-07-07 around 13:46 UTC+7.

#### Root Cause

The supplier view page lazy-loads `ArticlesRelationManager` via Livewire. When the relation manager boots its table, the Create Article header action builds its form from `ArticleResource::getFormSchema(forModal: true)`.

`getFormSchema()` was intended to exclude the Images and Public Catalog sections in modal context:

```php
...($forModal ? [] : [$imagesSection, $publicCatalogSection]),
```

However, `$imagesSection` and `$publicCatalogSection` were assigned before that conditional spread. PHP evaluated those assignments eagerly, so `SpatieMediaLibraryFileUpload::make(...)` ran even when `$forModal === true`.

If `filament/spatie-laravel-media-library-plugin` is listed in `composer.json` but not installed in the container, the lazy-loaded relation manager fails with:

```text
Class "Filament\Forms\Components\SpatieMediaLibraryFileUpload" not found
```

Observed stack trace:

```text
ArticleResource.php:171
ArticlesRelationManager.php:164 (getFormSchema(true))
InteractsWithTable.php:47 (table boot on lazy load)
```

#### Code Change

`ArticleResource::getFormSchema()` now builds Images and Public Catalog sections only when `forModal` is false:

```php
$fullFormOnlySections = $forModal ? [] : [
    Section::make('Images')
        ->schema([
            SpatieMediaLibraryFileUpload::make('product_images')
                ->collection('product_images')
                ->image()
                ->multiple(),
        ]),
    Section::make('Public Catalog')
        ->schema([
            // show_in_product_grid, list_price, suggestListPrice action
        ]),
];
```

When `$forModal === true`, no Spatie media upload component or Public Catalog component is constructed, so the supplier Articles tab can load without the plugin.

### Frontend

- UI: Supplier view page (`ViewSupplier`) relation manager tabs below infolist.
- Behavior: Articles tab lazy-loads via Livewire; the error toast no longer appears on load.
- Unchanged: Infolist sections, Invite Portal User action, Portal Users tab.

### Admin

- Path: Master Data -> Suppliers -> View supplier.
- Permission: Standard Filament app panel access, unchanged.
- Impact: Articles tab table and Create/Attach actions work in modal context without the Spatie plugin.

### Events / Plugins

- `ArticlesRelationManager`: Lazy-loaded on supplier view; triggers `getFormSchema(true)`.
- `ArticleResource::getFormSchema()`: Shared between full article form and supplier inline create.
- `filament/spatie-laravel-media-library-plugin`: Required only for non-modal Article form Images section.

### Business Rules

- Inline article create from a supplier still creates an `Article` and attaches pivot data (`supplier_sku`, `supplier_price`, etc.).
- Images and Public Catalog fields remain available on dedicated Article resource create/edit pages when the Spatie plugin is installed.
- Modal article inline forms intentionally omit Images and Public Catalog, matching `openspec/changes/archive/2026-07-04-add-public-product-catalog/tasks.md`.

## 4. Deployment

### Files

```text
app/Filament/Resources/ArticleResource.php
docs/FIX-IMPLEMENTATION-2026-07-07-supplier-detail.md
```

### Post-Deploy Commands

```bash
docker exec erpc-php php artisan optimize:clear
```

No migration or `composer install` is required for the supplier detail page fix itself.

Optional follow-up for product image uploads on the full Article form:

```bash
docker exec erpc-php composer install --no-interaction
docker exec erpc-php php artisan optimize:clear
docker exec erpc-php composer show filament/spatie-laravel-media-library-plugin
```

## 5. Verification Checklist

### Supplier Detail Page

- [ ] Open a supplier view page, for example IBOX / `CMP-0016`; no error toast appears.
- [ ] Supplier infolist shows code, company name, location, and financial settings.
- [ ] Articles tab loads with either an empty table or article rows.
- [ ] Portal Users tab loads.
- [ ] Invite Portal User button is visible when supplier has no active portal membership.

### Article Inline Create

- [ ] Click Create Article on Articles tab; modal opens without error.
- [ ] Submit creates article and attaches it to the supplier with pivot fields.

### Full Article Form

- [ ] After optional composer install, Article create/edit page shows the Images section.
- [ ] Product image upload works via Spatie Media Library.

### Automated Tests

```bash
./vendor/bin/pest --filter=ViewSupplierPageTest
```

- [ ] `renders the supplier view page`
- [ ] `renders relation managers on the supplier view page`
- [ ] `loads the supplier view page over HTTP`

## 6. Related Documentation

- `docs/FIX-IMPLEMENTATION-2026-07-07.md`: Same-day fixes for email, Mailpit, and portal invitation.
- `openspec/changes/archive/2026-07-04-add-public-product-catalog/tasks.md`: Images section design; excluded from inline modals.
- `app/Filament/Resources/SupplierResource/Pages/ViewSupplier.php`: Supplier view page.
- `app/Filament/Resources/SupplierResource/RelationManagers/ArticlesRelationManager.php`: Articles relation manager.

Applied on production: 2026-07-07
Author: Cursor agent session (production hotfix)
Status: Verified on production (`getFormSchema(true)` returns 9 fields; caches cleared)

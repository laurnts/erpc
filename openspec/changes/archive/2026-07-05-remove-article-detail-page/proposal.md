# Change: Remove the public article detail page

## Why

Product-owner decision (2026-07-05): the grid card already carries everything a visitor needs — image, name, tags, price display, availability badge, and the quantity + add-to-quote control — so a separate detail page adds a navigation hop without adding information. Removed for now; can return as its own change if richer per-article content (galleries, attributes) becomes worth a page.

## What Changes

- Remove the `/articles/{article}` route, the `ArticleDetail` Livewire component, and its view
- Grid cards no longer link anywhere; image and name render as plain elements
- Cart accumulation behavior is covered from the grid card instead

## Impact

- Affected specs: `public-catalog` (REMOVED: Product Detail Page; MODIFIED: Public Product Grid Homepage and Public Price Display to drop detail-page mentions)
- Affected code: `routes/web.php`, `app/Livewire/Catalog/ArticleDetail.php` (deleted), `resources/views/livewire/catalog/article-detail.blade.php` (deleted), `catalog-home.blade.php`, catalog tests

## 1. Persistence blocker fix (land first, independently verifiable)

- [ ] 1.1 Convert `EditBuyerRequest.php:59` from `items()->delete()` + per-line `RequestItem::create()` to in-place reconciliation (match by id: update changed via `save()`, remove missing via model `delete()`, create genuinely new).
- [ ] 1.2 Same conversion for `BuyerQuotesRelationManager.php:1690` and `:2105` (`items()->whereNull('request_item_id')->delete()`).
- [ ] 1.3 Same conversion for `SupplierQuotesRelationManager.php:1510`.
- [ ] 1.4 Same conversion for `SubmitQuoteCart.php:104` and `ItemsRelationManager.php:781`.
- [ ] 1.5 Regression tests: for each surface, assert the resulting item set (ids, values, count) is unchanged after an edit; assert a quantity edit now fires an `updated` event and a line removal fires a `deleted` event.

## 2. Line-item logging

- [ ] 2.1 Add `use LogsErpActivity` + `activityAttributes()` to the 8 `*Item` models with the audited field lists from the capability spec (drop derived intermediates written via `saveQuietly`).
- [ ] 2.2 Reverse the exclusion: audit `is_selected` on `SupplierQuoteItem` (supplier award flag).
- [ ] 2.3 `RequestItem` list = `quantity`, `unit_of_measure_id`, `unit`, `article_id`, `supplier_id`, `item_type` (no money columns exist).
- [ ] 2.4 Register the 8 item models in `Relation::enforceMorphMap` (`AppServiceProvider`) with stable snake_case keys (`buyer_quote_item`, …).

## 3. Enrichment & rendering (reuse Spatie, no bespoke shape)

- [ ] 3.1 Add a shared `tapActivity()` hook (small item concern) stamping `parent_type` / `parent_id` + resolved human line label into `properties`.
- [ ] 3.2 Resolve FK audited fields (`article_id`, `tax_code_id`, `supplier_id`, `unit_of_measure_id`) to human labels `old -> new` in `properties`, not raw ids.
- [ ] 3.3 On the `deleted` event, snapshot the full audited field set into old-values so removed line magnitude (qty × unit_price = line_total) is unambiguous.
- [ ] 3.4 Gate money-field row emission on a causal INPUT actually changing (quantity/unit_price/cost_price/tax_rate/tax_code_id/is_tax_inclusive), to avoid observer-recalc false positives on cosmetic edits.

## 4. Context, retention & header FX

- [ ] 4.1 Suppress item logging when `app()->runningInConsole()` or no `team_id` resolves (avoid orphan `team_id=null` rows from factories/seeders/imports/queues).
- [ ] 4.2 Add `exchange_rate` + `currency_id` to the six FX-bearing header `activityAttributes()` (BuyerQuote, BuyerOrder, BuyerInvoice, SupplierQuote, SupplierOrder, SupplierInvoice).
- [ ] 4.3 Set `config/activitylog.php` `delete_records_older_than_days` to `null`; document that `activitylog:clean` must never be scheduled for financial records.

## 5. Viewer

- [ ] 5.1 `EventLogResource`: verify item subject types populate the record-type filter.
- [ ] 5.2 Add a minimal detail-view line rendering parent context, reusing the native `attributes`/`old` shape (no new property-shape branch).

## 6. Tests & finalize

- [ ] 6.1 Quantity edit on an existing quote item → one diff row attributed to the parent + acting user.
- [ ] 6.2 Supplier-portal `unit_price` change → Supplier-actor money diff.
- [ ] 6.3 Unchanged / loop re-save → zero rows (`wasChanged` gating).
- [ ] 6.4 Cosmetic `notes` edit on a legacy-priced item → no money-change row.
- [ ] 6.5 Fresh quote build + quote→order + order→invoice conversion → baseline creates logged, no spurious churn.
- [ ] 6.6 `EditBuyerRequest` quantity edit → diff row, not N recreates (blocker regression).
- [ ] 6.7 Child/service sub-line removal from an existing quote → deletion logged with snapshot.
- [ ] 6.8 Whole-document delete → one header `deleted` row, zero item rows.
- [ ] 6.9 `article_id` / `tax_code_id` / `is_selected` swap → logged as a labeled diff.
- [ ] 6.10 Console / no-tenant item creation → zero rows.
- [ ] 6.11 EventLog detail page renders item property shape.
- [ ] 6.12 Run affected tests + `vendor/bin/pint --dirty`; then offer the full suite.

## 7. Timeline hookup (moved from add-request-activity-timeline task 6.2)

- [ ] 7.1 Swap the timeline's interim subject enumeration for this change's `parent_type`/`parent_id` predicate in `TimelineAudience`/`RequestTimelineSource`; line-level price/quantity entries then appear on the internal timeline with no further timeline changes.

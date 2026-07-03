# Implementation Tasks

## 0. Prerequisites
- [ ] 0.1 Archive `add-service-request-type` (deployed but unarchived) so its requirements land in base specs
- [ ] 0.2 Extend this change's `erp-trading-core` delta with `REMOVED: Request Type Classification` and `REMOVED: Service Request Child Items` (superseded here); re-validate

## 1. Schema & Backfill (non-breaking release)
- [ ] 1.1 Migration: add `request_items.item_type` string(20) default `'goods'`, index `(team_id, item_type)`
- [ ] 1.2 Backfill in the same migration: set every item's `item_type` from its request's `request_type`
- [ ] 1.3 Feature test: backfill maps goods→goods items, services→services items

## 2. Enum & Models
- [ ] 2.1 Rename `RequestType` → `ItemType` (class + imports; DB values unchanged); keep all five capability methods and `casesUsingAcceptanceReports()`
- [ ] 2.2 `RequestItem`: add `item_type` fillable + cast, capability passthroughs (`supportsItemHierarchy()` etc.), enforce child items inherit parent type (observer or mutator)
- [ ] 2.3 `Request`: add `hasGoodsItems()` / `hasServiceItems()`; rewire completion (`isFullyShipped` scoped to goods items, acceptance coverage scoped to service main items); matching validation per item type; remove request-level capability passthroughs
- [ ] 2.4 `BuyerQuoteItem::filterForTotals()`: drop the `$hasItemHierarchy` parameter — always exclude child lines; update `collectTotals()` and all callers (BuyerQuote, SupplierQuote, SupplierOrdersRelationManager, blades, unit tests)
- [ ] 2.5 Unit/feature tests for 2.2–2.4 (mixed request completion, totals with mixed items)

## 3. Request & Item Forms
- [ ] 3.1 `RequestResource`: remove `request_type` select from create/edit
- [ ] 3.2 `ItemsRelationManager`: per-item `item_type` toggle (default goods); child-items section visible when the *item* is a service item; child rows created with parent's type
- [ ] 3.3 Customer portal `CreateCustomerRequest`: replace request-type select with per-item type selection; `CustomerRequestResource` table shows item-type summary badge (e.g. "Goods", "Services", "Mixed")
- [ ] 3.4 Feature tests: create mixed request via admin and portal

## 4. Fulfillment Gating
- [ ] 4.1 `ShipmentsRelationManager` (admin + portal): `canViewForRecord` → `hasGoodsItems()`; shipment item pickers filter to goods items
- [ ] 4.2 `AcceptanceReportsRelationManager` + `AcceptanceReportResource`: gate on `hasServiceItems()`; request dropdown filters to requests with service items; report item picker filters to service items
- [ ] 4.3 Feature tests: mixed request shows both tabs; pickers exclude wrong-type items

## 5. Quoting & QE
- [ ] 5.1 `GenerateSupplierQuotesForRequest`: child-item generation keyed to `item->supportsItemHierarchy()`
- [ ] 5.2 `SupplierQuotesRelationManager` / `BuyerQuotesRelationManager`: hierarchy branches keyed per item; job-progress fields visible when the quote's request has ≥1 service item
- [ ] 5.3 `QuotationEvaluationForm`: guard on `hasGoodsItems()`; evaluation lists goods items only
- [ ] 5.4 Feature tests: mixed-request supplier quote (children only under service mains), QE excludes service items

## 6. Documents & Views
- [ ] 6.1 P&L PDF + `pnl-selected-items` blade: hierarchy notes keyed to presence of child lines, not request type
- [ ] 6.2 Verify quote/order/invoice PDFs render mixed item lists correctly
- [ ] 6.3 Feature test: P&L totals on a mixed request

## 7. Column Drop (follow-up release)
- [ ] 7.1 Migration: drop `requests.request_type` (+ index); `down()` re-derives type (all-service → services, else goods)
- [ ] 7.2 Sweep for any remaining `request_type` references (code, queries, exports)

## 8. Validation & Wrap-up
- [ ] 8.1 `vendor/bin/pint --dirty`; PHPStan clean on touched files
- [ ] 8.2 Full test suite green (`php artisan test --compact`)
- [ ] 8.3 `openspec validate move-fulfillment-to-item-level --strict` passes; archive when deployed

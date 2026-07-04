# Change: Move Goods/Services Fulfillment to Item Level

## Why

Mixed goods + service deals (e.g. equipment + installation on one order) are frequent, core business. Today `request_type` is a single request-level switch: one request is either Goods (shipments, flat items, quotation evaluation) or Services (acceptance reports, main/child items, no QE). Mixed deals must be split into two linked requests, fragmenting quotes, orders, invoicing, and P&L for what is commercially one deal.

The 2026-07-03 capability refactor (`RequestType::supportsItemHierarchy()`, `usesAcceptanceReports()`, `requiresShipments()`, `hasJobProgress()`, `usesQuotationEvaluation()`) single-sourced every type-driven rule as the prerequisite for this change. This change moves the type — and therefore fulfillment — to the request-item level.

## What Changes

- **ADDED**: `item_type` (goods | services) on `request_items`; each item declares its own fulfillment channel. Defaults to goods. Child items inherit their parent's type.
- **REMOVED**: The request-level Goods/Services selector on request create/edit (admin panel and customer portal). The `requests.request_type` column is dropped after backfill.
- **MODIFIED**: Request behavior derives from its items:
  - Shipments tab shown when the request has ≥1 goods item; shipment item pickers offer goods items only.
  - Acceptance Reports tab shown when the request has ≥1 service item; report item pickers offer service items only.
  - Both tabs coexist on mixed requests.
- **MODIFIED**: Stage matching validation requires goods items and service *main* items to be matched (service child items exempt). Derived request-level completion ("every item satisfied through its own channel") is deferred to a follow-up change — stage progression remains manual, as before.
- **MODIFIED**: Quotation Evaluation is available when the request has ≥1 goods item and covers goods items only; service items never appear in QE.
- **MODIFIED**: Item hierarchy (main/child) and job-progress payment terms attach to service *items* rather than service *requests*. Totals continue to exclude child items of service main items.
- **BREAKING**: Existing `requests.request_type` is migrated by copying the type onto each of the request's items, then dropped. External consumers of `request_type` (none known in-repo outside the migrated call sites) must switch to item-level queries.

## Relationship to Existing Changes

- **`add-service-request-type`** (deployed but unarchived, 0/32 tasks checked): fully implemented in code; must be **archived before applying this change** so its requirements land in the base specs. This change then supersedes its request-level classification: `Request Type Classification`, `Service Request Child Items`, and the request-level parts of `Acceptance Reports for Service Requests` are replaced by the item-level requirements added here. At apply time, add the corresponding `REMOVED`/`MODIFIED` deltas for those requirements (they cannot be referenced in deltas until the archive lands them in base specs).
- **`add-customer-portal`** (75/76 tasks): the portal request-creation form currently asks for a request type; this change replaces that with per-item type selection.

## Impact

- **Affected specs**: `erp-trading-core` (requests, items, lifecycle), `erp-shipments` (eligibility), `erp-quoting` (QE scope, supplier quote child-item generation)
- **Affected code**:
  - `app/Enums/RequestType.php` → renamed `ItemType`; capability methods unchanged (DB values `goods`/`services` retained)
  - `app/Models/RequestItem.php` — `item_type` column, cast, capability passthroughs
  - `app/Models/Request.php` — derived helpers (`hasGoodsItems()`, `hasServiceItems()`, per-channel completion); remove request-level capability passthroughs
  - `RequestResource`, `CustomerRequestResource` create/edit forms — remove request-level selector; `ItemsRelationManager` — per-item type toggle, hierarchy section gated per item
  - `ShipmentsRelationManager` (admin + portal), `AcceptanceReportsRelationManager`, `AcceptanceReportResource` — presence-based gating and item-filtered pickers
  - `SupplierQuotesRelationManager`, `BuyerQuotesRelationManager`, `GenerateSupplierQuotesForRequest` — hierarchy/job-progress logic keyed off the item, not the request
  - `QuotationEvaluationForm` — goods-items-only scope
  - `BuyerQuoteItem::filterForTotals` — hierarchy exclusion becomes unconditional (child items always excluded from totals; only service items have children)
  - PDF/infolist blades (`profit-and-loss`, `pnl-selected-items`)
  - Migrations: add `request_items.item_type`, backfill from `requests.request_type`, drop `requests.request_type`
- **Breaking changes**: request-level `request_type` removed (see above); one-way data migration with reversible down() that re-derives request type from item majority

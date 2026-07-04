# Change: Derived per-channel fulfillment completion and goods-only QE ranking

## Why

The item-level fulfillment change (`2026-07-04-move-fulfillment-to-item-level`) explicitly deferred request-level completion tracking: today nothing tells a user that a mixed deal is "done" — stage progression is fully manual, and a request can be marked completed with unshipped goods or unaccepted services. Separately, the Quotation Evaluation ranks supplier quotes by `total_base` (QuotationEvaluationForm.php:291), which on mixed requests includes services lines in what is spec-defined as a goods-only comparison — a supplier's service pricing can distort the goods ranking.

## What Changes

- Derive per-channel completion on Request: the goods channel is complete when every goods main item is fully covered by shipments; the services channel is complete when every services main item is covered by an acceptance report; a channel with no items is vacuously complete
- Derive a request-level fulfillment status ("fulfilled" when all present channels are complete) — display it on the request view and list
- Gate the final/completed stage transition on derived fulfillment (stage progression otherwise stays manual; no auto-advance)
- Rank supplier quotes in Quotation Evaluation by goods-lines-only subtotal on mixed requests (comparison already excludes services lines; the ordering now matches)

## Impact

- Affected specs: `erp-trading-core` (Item-Level Fulfillment Channels — deferral removed; new Derived Fulfillment Completion requirement), `erp-quoting` (new goods-only ranking requirement)
- Affected code:
  - `app/Models/Request.php` — derived completion accessors (channel rollups over shipments / acceptance reports)
  - Stage transition validation (wherever the completed-stage guard lives) — add fulfillment gate
  - `app/Filament/Resources/RequestResource` view/list — fulfillment status display
  - `app/Livewire/QuotationEvaluationForm.php:291` — ranking basis
  - Tests: extend `tests/Feature/Erp/ItemLevelFulfillmentTest.php`; new QE ranking test

## Decisions (approved 2026-07-04)

1. Goods completion basis: **full quantity coverage** by shipments, using existing partial-shipment data.
2. Completed-stage gate: **hard block** — no admin override; the error names the incomplete channel(s).
3. QE ranking on mixed requests: **sort by goods-only subtotal AND display both** the goods subtotal and the full quote total per supplier.
